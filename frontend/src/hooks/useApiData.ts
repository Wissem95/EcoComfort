import { useState, useEffect, useCallback } from 'react';
import apiService from '../services/api';
import type { 
  DashboardOverview, 
  SensorDataResponse, 
  AlertsResponse, 
  EnergyAnalytics,
  SensorInfo,
  EventsResponse,
  EventData 
} from '../services/api';

interface UseApiDataReturn {
  // Data states
  overview: DashboardOverview | null;
  sensors: SensorInfo[];
  alerts: AlertsResponse | null;
  energyAnalytics: EnergyAnalytics | null;
  events: EventsResponse | null;
  
  // Loading states
  overviewLoading: boolean;
  sensorsLoading: boolean;
  alertsLoading: boolean;
  energyLoading: boolean;
  eventsLoading: boolean;
  
  // Error states
  overviewError: string | null;
  sensorsError: string | null;
  alertsError: string | null;
  energyError: string | null;
  eventsError: string | null;
  
  // Actions
  refreshOverview: () => Promise<void>;
  refreshSensors: () => Promise<void>;
  refreshAlerts: () => Promise<void>;
  refreshEnergyAnalytics: (days?: number) => Promise<void>;
  refreshEvents: (params?: any) => Promise<void>;
  refreshAll: () => Promise<void>;
  
  // Utility
  isAnyLoading: boolean;
  hasAnyError: boolean;
}

export const useApiData = (): UseApiDataReturn => {
  // Data states
  const [overview, setOverview] = useState<DashboardOverview | null>(null);
  const [sensors, setSensors] = useState<SensorInfo[]>([]);
  const [alerts, setAlerts] = useState<AlertsResponse | null>(null);
  const [energyAnalytics, setEnergyAnalytics] = useState<EnergyAnalytics | null>(null);
  const [events, setEvents] = useState<EventsResponse | null>(null);

  // Loading states
  const [overviewLoading, setOverviewLoading] = useState(false);
  const [sensorsLoading, setSensorsLoading] = useState(false);
  const [alertsLoading, setAlertsLoading] = useState(false);
  const [energyLoading, setEnergyLoading] = useState(false);
  const [eventsLoading, setEventsLoading] = useState(false);

  // Error states
  const [overviewError, setOverviewError] = useState<string | null>(null);
  const [sensorsError, setSensorsError] = useState<string | null>(null);
  const [alertsError, setAlertsError] = useState<string | null>(null);
  const [energyError, setEnergyError] = useState<string | null>(null);
  const [eventsError, setEventsError] = useState<string | null>(null);

  // Refresh functions
  const refreshOverview = useCallback(async () => {
    try {
      setOverviewLoading(true);
      setOverviewError(null);
      const data = await apiService.getDashboardOverview();
      setOverview(data);
    } catch (error) {
      console.error('Failed to fetch dashboard overview:', error);
      setOverviewError(error instanceof Error ? error.message : 'Failed to fetch overview');
    } finally {
      setOverviewLoading(false);
    }
  }, []);

  const refreshSensors = useCallback(async () => {
    try {
      setSensorsLoading(true);
      setSensorsError(null);
      const data = await apiService.getSensorData();
      setSensors(data.sensors);
    } catch (error) {
      console.error('Failed to fetch sensor data:', error);
      setSensorsError(error instanceof Error ? error.message : 'Failed to fetch sensors');
      setSensors([]); // Clear sensors on error
    } finally {
      setSensorsLoading(false);
    }
  }, []);

  const refreshAlerts = useCallback(async () => {
    try {
      setAlertsLoading(true);
      setAlertsError(null);
      const data = await apiService.getAlerts({ limit: 20 });
      setAlerts(data);
    } catch (error) {
      console.error('Failed to fetch alerts:', error);
      setAlertsError(error instanceof Error ? error.message : 'Failed to fetch alerts');
    } finally {
      setAlertsLoading(false);
    }
  }, []);

  const refreshEnergyAnalytics = useCallback(async (days: number = 7) => {
    try {
      setEnergyLoading(true);
      setEnergyError(null);
      const data = await apiService.getEnergyAnalytics(days);
      setEnergyAnalytics(data);
    } catch (error) {
      console.error('Failed to fetch energy analytics:', error);
      setEnergyError(error instanceof Error ? error.message : 'Failed to fetch energy analytics');
    } finally {
      setEnergyLoading(false);
    }
  }, []);

  const refreshEvents = useCallback(async (params: {
    page?: number;
    limit?: number;
    severity?: 'info' | 'warning' | 'critical';
    type?: string;
    acknowledged?: boolean;
    room_id?: string;
    sensor_id?: string;
    start_date?: string;
    end_date?: string;
  } = {}) => {
    try {
      setEventsLoading(true);
      setEventsError(null);
      const data = await apiService.getEvents(params);
      setEvents(data);
    } catch (error) {
      console.error('Failed to fetch events:', error);
      setEventsError(error instanceof Error ? error.message : 'Failed to fetch events');
    } finally {
      setEventsLoading(false);
    }
  }, []);

  const refreshAll = useCallback(async () => {
    await Promise.all([
      refreshOverview(),
      refreshSensors(),
      refreshAlerts(),
      refreshEnergyAnalytics()
    ]);
  }, [refreshOverview, refreshSensors, refreshAlerts, refreshEnergyAnalytics]);

  // Auto-refresh on mount
  useEffect(() => {
    refreshAll();
  }, [refreshAll]);

  // Auto-refresh sensors every 30 seconds
  useEffect(() => {
    const interval = setInterval(() => {
      refreshSensors();
    }, 30000);

    return () => clearInterval(interval);
  }, [refreshSensors]);

  // Auto-refresh overview every 60 seconds
  useEffect(() => {
    const interval = setInterval(() => {
      refreshOverview();
    }, 60000);

    return () => clearInterval(interval);
  }, [refreshOverview]);

  // Computed states
  const isAnyLoading = overviewLoading || sensorsLoading || alertsLoading || energyLoading || eventsLoading;
  const hasAnyError = !!(overviewError || sensorsError || alertsError || energyError || eventsError);

  return {
    // Data
    overview,
    sensors,
    alerts,
    energyAnalytics,
    events,
    
    // Loading states
    overviewLoading,
    sensorsLoading,
    alertsLoading,
    energyLoading,
    eventsLoading,
    
    // Error states
    overviewError,
    sensorsError,
    alertsError,
    energyError,
    eventsError,
    
    // Actions
    refreshOverview,
    refreshSensors,
    refreshAlerts,
    refreshEnergyAnalytics,
    refreshEvents,
    refreshAll,
    
    // Utility
    isAnyLoading,
    hasAnyError
  };
};

export default useApiData;