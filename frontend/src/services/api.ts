import type { SensorData } from '../types';

interface ApiResponse<T> {
  data?: T;
  message?: string;
}

interface Organization {
  name: string;
  surface_m2: number;
  target_percent: number;
}

interface Infrastructure {
  total_buildings: number;
  total_rooms: number;
  total_sensors: number;
  active_sensors: number;
  sensor_uptime: number;
}

interface Energy {
  total_energy_loss_kwh: number;
  total_cost: number;
  rooms_with_open_doors: number;
}

interface AlertStats {
  unacknowledged: number;
  critical: number;
}

interface DashboardOverview {
  organization: Organization;
  infrastructure: Infrastructure;
  energy: Energy;
  alerts: AlertStats;
}

interface SensorInfo {
  sensor_id: string;
  name: string;
  position: string;
  room: {
    id: string;
    name: string;
    building_name: string;
  };
  battery_level: number;
  is_online: boolean;
  last_seen: string | null;
  data: SensorData | null;
}

interface SensorDataResponse {
  sensors: SensorInfo[];
}

interface Alert {
  id: string;
  type: string;
  severity: 'info' | 'warning' | 'critical';
  message: string;
  acknowledged: boolean;
  cost_impact?: number;
  created_at: string;
  room?: {
    name: string;
  };
  sensor?: {
    name: string;
  };
}

interface AlertsResponse {
  alerts: Alert[];
  pagination: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  stats: {
    unacknowledged: number;
    critical: number;
  };
}

interface RoomAnalytic {
  room_id: string;
  room_name: string;
  building_name: string;
  energy_loss_kwh: number;
  cost: number;
  events_count: number;
  average_duration: number;
  efficiency_score: number;
  potential_savings: any;
}

interface EnergyAnalytics {
  total_energy_loss_kwh: number;
  total_cost: number;
  room_analytics: RoomAnalytic[];
  efficiency: {
    target_percent: number;
    actual_percent: number;
    goal_achieved: boolean;
    improvement_needed: number;
  };
}

class ApiService {
  private baseURL: string;
  private authToken: string | null = null;

  constructor() {
    this.baseURL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';
    this.authToken = localStorage.getItem('auth_token');
  }

  // Get the appropriate endpoint prefix (always use authenticated endpoints)
  private getEndpointPrefix(): string {
    // Always use authenticated endpoints with real data
    return '';
  }

  private async makeRequest<T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<T> {
    const url = `${this.baseURL}${endpoint}`;
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(this.authToken && { 'Authorization': `Bearer ${this.authToken}` }),
      ...options.headers,
    };

    try {
      const response = await fetch(url, {
        ...options,
        headers,
      });

      if (!response.ok) {
        throw new Error(`API Error: ${response.status} ${response.statusText}`);
      }

      return await response.json();
    } catch (error) {
      console.error(`API request failed for ${endpoint}:`, error);
      throw error;
    }
  }

  // Dashboard Overview
  async getDashboardOverview(): Promise<DashboardOverview> {
    const prefix = this.getEndpointPrefix();
    return this.makeRequest<DashboardOverview>(`${prefix}/dashboard/overview`);
  }

  // Sensor Data
  async getSensorData(): Promise<SensorDataResponse> {
    const prefix = this.getEndpointPrefix();
    return this.makeRequest<SensorDataResponse>(`${prefix}/dashboard/sensor-data`);
  }

  // Alerts
  async getAlerts(params: {
    page?: number;
    limit?: number;
    severity?: string;
    acknowledged?: boolean;
  } = {}): Promise<AlertsResponse> {
    const queryParams = new URLSearchParams();
    
    if (params.page) queryParams.append('page', params.page.toString());
    if (params.limit) queryParams.append('limit', params.limit.toString());
    if (params.severity) queryParams.append('severity', params.severity);
    if (params.acknowledged !== undefined) queryParams.append('acknowledged', params.acknowledged.toString());

    const prefix = this.getEndpointPrefix();
    const endpoint = `${prefix}/dashboard/alerts${queryParams.toString() ? '?' + queryParams.toString() : ''}`;
    return this.makeRequest<AlertsResponse>(endpoint);
  }

  // Energy Analytics
  async getEnergyAnalytics(days: number = 7): Promise<EnergyAnalytics> {
    const prefix = this.getEndpointPrefix();
    return this.makeRequest<EnergyAnalytics>(`${prefix}/dashboard/energy-analytics?days=${days}`);
  }

  // Room Details
  async getRoomDetails(roomId: string): Promise<any> {
    return this.makeRequest<any>(`/dashboard/room/${roomId}`);
  }

  // Sensor History
  async getSensorHistory(
    sensorId: string,
    params: {
      start_date?: string;
      end_date?: string;
      interval?: '1m' | '5m' | '15m' | '1h' | '6h' | '1d';
      metrics?: string[];
    } = {}
  ): Promise<any> {
    const queryParams = new URLSearchParams();
    
    if (params.start_date) queryParams.append('start_date', params.start_date);
    if (params.end_date) queryParams.append('end_date', params.end_date);
    if (params.interval) queryParams.append('interval', params.interval);
    if (params.metrics) params.metrics.forEach(metric => queryParams.append('metrics[]', metric));

    const endpoint = `/sensors/${sensorId}/history${queryParams.toString() ? '?' + queryParams.toString() : ''}`;
    return this.makeRequest<any>(endpoint);
  }

  // Get all sensors
  async getSensors(params: {
    page?: number;
    limit?: number;
    room_id?: string;
    status?: 'active' | 'inactive' | 'offline';
  } = {}): Promise<any> {
    const queryParams = new URLSearchParams();
    
    if (params.page) queryParams.append('page', params.page.toString());
    if (params.limit) queryParams.append('limit', params.limit.toString());
    if (params.room_id) queryParams.append('room_id', params.room_id);
    if (params.status) queryParams.append('status', params.status);

    const endpoint = `/sensors${queryParams.toString() ? '?' + queryParams.toString() : ''}`;
    return this.makeRequest<any>(endpoint);
  }

  // Acknowledge alert
  async acknowledgeAlert(eventId: string): Promise<ApiResponse<any>> {
    return this.makeRequest<ApiResponse<any>>(`/dashboard/alerts/${eventId}/acknowledge`, {
      method: 'POST',
    });
  }

  // Gamification data
  async getGamificationData(): Promise<any> {
    const prefix = this.getEndpointPrefix();
    return this.makeRequest<any>(`${prefix}/dashboard/gamification`);
  }

  // Set authentication token
  setAuthToken(token: string) {
    this.authToken = token;
    localStorage.setItem('auth_token', token);
  }

  // Clear authentication token
  clearAuthToken() {
    this.authToken = null;
    localStorage.removeItem('auth_token');
  }

  // Authentication methods
  async login(email: string, password: string): Promise<{ token: string; user: any }> {
    const response = await this.makeRequest<{ data: { access_token: string; user: any } }>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({
        email,
        password,
      }),
    });
    
    return {
      token: response.data.access_token,
      user: response.data.user,
    };
  }

  async register(
    name: string,
    email: string,
    password: string,
    password_confirmation: string,
    organization_name: string
  ): Promise<{ token: string; user: any }> {
    const response = await this.makeRequest<{ data: { access_token: string; user: any } }>('/auth/register', {
      method: 'POST',
      body: JSON.stringify({
        name,
        email,
        password,
        password_confirmation,
        organization_name,
      }),
    });
    
    return {
      token: response.data.access_token,
      user: response.data.user,
    };
  }

  async logout(): Promise<void> {
    await this.makeRequest<any>('/auth/logout', {
      method: 'POST',
    });
    this.clearAuthToken();
  }

  async getUserProfile(): Promise<any> {
    const response = await this.makeRequest<{ data: any }>('/auth/user');
    return response.data;
  }

  // Health check
  async healthCheck(): Promise<boolean> {
    try {
      await this.makeRequest<any>('/health');
      return true;
    } catch {
      return false;
    }
  }
}

// Create singleton instance
const apiService = new ApiService();
export default apiService;
export type {
  DashboardOverview,
  SensorDataResponse,
  SensorInfo,
  AlertsResponse,
  Alert,
  EnergyAnalytics,
  RoomAnalytic,
};