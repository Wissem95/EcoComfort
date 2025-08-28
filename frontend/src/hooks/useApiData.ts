import { useState, useEffect, useCallback } from 'react';
import apiService from '../services/api';
import type { 
  DashboardOverview, 
  SensorDataResponse, 
  AlertsResponse, 
  EnergyAnalytics,
  SensorInfo 
} from '../services/api';

interface UseApiDataReturn {
  // Data states
  overview: DashboardOverview | null;
  sensors: SensorInfo[];
  alerts: AlertsResponse | null;
  energyAnalytics: EnergyAnalytics | null;
  
  // Loading states
  overviewLoading: boolean;
  sensorsLoading: boolean;
  alertsLoading: boolean;
  energyLoading: boolean;
  
  // Error states
  overviewError: string | null;
  sensorsError: string | null;
  alertsError: string | null;
  energyError: string | null;
  
  // Actions
  refreshOverview: () => Promise<void>;
  refreshSensors: () => Promise<void>;
  refreshAlerts: () => Promise<void>;
  refreshEnergyAnalytics: (days?: number) => Promise<void>;
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

  // Loading states
  const [overviewLoading, setOverviewLoading] = useState(false);
  const [sensorsLoading, setSensorsLoading] = useState(false);
  const [alertsLoading, setAlertsLoading] = useState(false);
  const [energyLoading, setEnergyLoading] = useState(false);

  // Error states
  const [overviewError, setOverviewError] = useState<string | null>(null);
  const [sensorsError, setSensorsError] = useState<string | null>(null);
  const [alertsError, setAlertsError] = useState<string | null>(null);
  const [energyError, setEnergyError] = useState<string | null>(null);

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
  const isAnyLoading = overviewLoading || sensorsLoading || alertsLoading || energyLoading;
  const hasAnyError = !!(overviewError || sensorsError || alertsError || energyError);

  return {
    // Data
    overview,
    sensors,
    alerts,
    energyAnalytics,
    
    // Loading states
    overviewLoading,
    sensorsLoading,
    alertsLoading,
    energyLoading,
    
    // Error states
    overviewError,
    sensorsError,
    alertsError,
    energyError,
    
    // Actions
    refreshOverview,
    refreshSensors,
    refreshAlerts,
    refreshEnergyAnalytics,
    refreshAll,
    
    // Utility
    isAnyLoading,
    hasAnyError
  };
};

export default useApiData;