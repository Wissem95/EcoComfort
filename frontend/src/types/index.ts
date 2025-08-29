// API Types
export interface User {
  id: string;
  name: string;
  email: string;
  role: 'admin' | 'manager' | 'user';
  points: number;
  organization_id: string;
  created_at: string;
  updated_at: string;
}

export interface Organization {
  id: string;
  name: string;
  surface_m2: number;
  target_percent: number;
}

export interface Building {
  id: string;
  name: string;
  address: string;
  floors_count: number;
}

export interface Room {
  id: string;
  name: string;
  type: 'office' | 'meeting' | 'corridor' | 'bathroom' | 'kitchen' | 'storage' | 'other';
  floor: number;
  surface_m2: number;
  target_temperature: number;
  target_humidity: number;
  building: Building;
}

export interface Sensor {
  id: string;
  name: string;
  mac_address: string;
  position: 'door' | 'window' | 'wall' | 'ceiling' | 'floor';
  battery_level: number;
  is_active: boolean;
  is_online: boolean;
  has_usable_data: boolean;
  last_seen_at?: string;
  room: Room;
  latest_data?: SensorData;
}

export interface SensorData {
  timestamp: string;
  temperature?: number;
  humidity?: number;
  acceleration_x?: number;
  acceleration_y?: number;
  acceleration_z?: number;
  door_state?: boolean;
  energy_loss_watts?: number;
}

export interface Event {
  id: string;
  type: 'door_open' | 'window_open' | 'temperature_high' | 'temperature_low' | 'humidity_high' | 'humidity_low' | 'energy_loss' | 'battery_low';
  severity: 'info' | 'warning' | 'critical';
  message: string;
  cost_impact?: number;
  acknowledged: boolean;
  acknowledged_at?: string;
  acknowledged_by?: User;
  data?: any;
  sensor: Sensor;
  room: Room;
  created_at: string;
}

// Gamification Types
export interface GamificationLevel {
  current_level: number;
  next_level: number;
  total_points: number;
  points_for_current: number;
  points_for_next: number;
  points_to_next: number;
  progress_percent: number;
  is_max_level: boolean;
}

export interface Badge {
  id: string;
  name: string;
  description: string;
  earned: boolean;
  progress: number;
  threshold: number;
  progress_percent: number;
  points: number;
  earned_at?: string;
}

export interface LeaderboardEntry {
  rank: number;
  user_id: string;
  name: string;
  total_points: number;
  period_points: number;
  period_actions: number;
  level: number;
  badges_count: number;
}

export interface Challenge {
  id: string;
  name: string;
  description: string;
  target: number;
  metric: 'points' | 'actions' | 'energy_saved';
  start_date: string;
  end_date: string;
  reward_points: number;
  status: 'active' | 'completed' | 'expired';
  progress: number;
  progress_percent: number;
  participants: string[];
}

// Dashboard Types
export interface DashboardOverview {
  organization: Organization;
  infrastructure: {
    total_buildings: number;
    total_rooms: number;
    total_sensors: number;
    active_sensors: number;
    sensor_uptime: number;
  };
  energy: {
    total_energy_loss_kwh: number;
    total_cost: number;
    rooms_with_open_doors: number;
  };
  alerts: {
    total_alerts: number;
    total_cost: number;
    critical_count: number;
    warning_count: number;
    info_count: number;
    unacknowledged: number;
  };
}

// Notification Types
export type NotificationState = 'normal' | 'alerte_info' | 'alerte_warning' | 'alerte_critical' | 'action_auto';

export interface Notification {
  id: string;
  title: string;
  message: string;
  type: NotificationState;
  timestamp: string;
  actions?: NotificationAction[];
  auto_resolve?: boolean;
  resolve_timeout?: number;
}

export interface NotificationAction {
  id: string;
  label: string;
  type: 'accept' | 'reject' | 'snooze';
  reward?: {
    points: number;
    description: string;
  };
}

// PMV/PPD Comfort Types
export interface ComfortScore {
  pmv: number; // Predicted Mean Vote (-3 to +3)
  ppd: number; // Predicted Percentage of Dissatisfied (5-100%)
  category: 'cold' | 'cool' | 'neutral' | 'warm' | 'hot';
  comfort_level: number; // 0-100%
  recommendations: string[];
}

// Chart Data Types
export interface ChartDataPoint {
  timestamp: string;
  temperature?: number;
  humidity?: number;
  energy_loss?: number;
  door_state?: boolean;
}

export interface EnergyAnalytics {
  total_energy_loss_kwh: number;
  total_cost: number;
  room_analytics: RoomAnalytics[];
  efficiency: {
    target_percent: number;
    actual_percent: number;
    goal_achieved: boolean;
    improvement_needed: number;
  };
}

export interface RoomAnalytics {
  room_id: string;
  room_name: string;
  building_name: string;
  energy_loss_kwh: number;
  cost: number;
  events_count: number;
  average_duration: number;
  efficiency_score: number;
  potential_savings: {
    daily_average_kwh: number;
    monthly_projection_kwh: number;
    yearly_projection_kwh: number;
    yearly_cost: number;
    co2_emissions_kg_yearly: number;
  };
}

// WebSocket Event Types
export interface WebSocketEvent {
  type: string;
  data: any;
  timestamp: string;
}

export interface SensorDataUpdateEvent extends WebSocketEvent {
  type: 'sensor.data.updated';
  data: {
    sensor_id: string;
    room_id: string;
    building_id: string;
    organization_id: string;
    sensor_name: string;
    room_name: string;
    data: SensorData;
  };
}

export interface AlertCreatedEvent extends WebSocketEvent {
  type: 'alert.created';
  data: {
    event_id: string;
    sensor_id: string;
    room_id: string;
    type: string;
    severity: string;
    message: string;
    cost_impact?: number;
    room_name: string;
    sensor_name: string;
  };
}

// Gamification Level System
export type GamificationLevelName = 'beginner' | 'saver' | 'expert' | 'champion' | 'master';

export interface GamificationLevelConfig {
  name: GamificationLevelName;
  icon: string;
  title: string;
  minPoints: number;
  maxPoints: number;
  color: string;
  benefits: string[];
}

// Negotiation System Types
export interface Negotiation {
  id: string;
  title: string;
  description: string;
  action: string;
  duration_minutes: number;
  reward: {
    points: number;
    additional_reward?: string;
  };
  expires_at: string;
  status: 'pending' | 'accepted' | 'rejected' | 'completed' | 'expired';
  created_at: string;
}

// Settings Types
export interface UserSettings {
  notifications: {
    push_enabled: boolean;
    email_enabled: boolean;
    critical_only: boolean;
    quiet_hours: {
      enabled: boolean;
      start: string;
      end: string;
    };
  };
  display: {
    theme: 'light' | 'dark' | 'auto';
    temperature_unit: 'celsius' | 'fahrenheit';
    currency: 'EUR' | 'USD';
    language: 'fr' | 'en';
  };
  gamification: {
    enabled: boolean;
    show_leaderboard: boolean;
    show_notifications: boolean;
  };
}

// App State Types
export interface AppState {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  error: string | null;
  darkMode: boolean;
  settings: UserSettings;
  notifications: Notification[];
  webSocketConnected: boolean;
  lastDataUpdate: string | null;
}

// API Response Types
export interface ApiResponse<T> {
  data?: T;
  message?: string;
  error?: string;
  pagination?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

// Error Types
export interface ApiError {
  message: string;
  errors?: Record<string, string[]>;
  status?: number;
}