import React, { useState, useEffect } from 'react';
import { Clock, User, DoorOpen, DoorClosed, AlertTriangle, CheckCircle, XCircle } from 'lucide-react';
import apiService from '../services/api';

interface DoorStateHistoryProps {
  sensorId: string;
  className?: string;
}

interface ConfirmationRecord {
  id: string;
  confirmed_state: string;
  previous_state: string;
  previous_certainty: 'CERTAIN' | 'PROBABLE' | 'UNCERTAIN';
  sensor_position: {
    x: number;
    y: number;
    z: number;
  };
  confidence_before: number | null;
  user_notes: string | null;
  created_at: string;
  user: {
    id: string;
    name: string;
  };
}

const DoorStateHistory: React.FC<DoorStateHistoryProps> = ({ sensorId, className = '' }) => {
  const [history, setHistory] = useState<ConfirmationRecord[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    loadHistory();
  }, [sensorId]);

  const loadHistory = async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await apiService.getDoorStateConfirmationHistory(sensorId);
      if (response.success) {
        setHistory(response.data);
      } else {
        setError('Erreur lors du chargement de l\'historique');
      }
    } catch (err) {
      console.error('Failed to load confirmation history:', err);
      setError('Erreur lors du chargement de l\'historique');
    } finally {
      setLoading(false);
    }
  };

  const formatDateTime = (dateString: string) => {
    const date = new Date(dateString);
    return {
      date: date.toLocaleDateString('fr-FR'),
      time: date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
    };
  };

  const getStateIcon = (state: string) => {
    switch (state) {
      case 'closed':
        return <DoorClosed size={16} className="text-green-600" />;
      case 'opened':
        return <DoorOpen size={16} className="text-red-600" />;
      default:
        return <AlertTriangle size={16} className="text-orange-600" />;
    }
  };

  const getCertaintyIcon = (certainty: string) => {
    switch (certainty) {
      case 'CERTAIN':
        return <CheckCircle size={14} className="text-green-500" />;
      case 'PROBABLE':
        return <AlertTriangle size={14} className="text-orange-500" />;
      case 'UNCERTAIN':
        return <XCircle size={14} className="text-red-500" />;
      default:
        return <XCircle size={14} className="text-gray-500" />;
    }
  };

  const getCorrectionType = (record: ConfirmationRecord) => {
    return record.previous_state !== record.confirmed_state ? 'correction' : 'confirmation';
  };

  if (loading) {
    return (
      <div className={`bg-white rounded-lg shadow-md p-4 ${className}`}>
        <h3 className="text-lg font-semibold mb-4 text-gray-800">Historique des confirmations</h3>
        <div className="flex items-center justify-center py-8">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
          <span className="ml-2 text-gray-600">Chargement...</span>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className={`bg-white rounded-lg shadow-md p-4 ${className}`}>
        <h3 className="text-lg font-semibold mb-4 text-gray-800">Historique des confirmations</h3>
        <div className="text-red-600 text-sm">{error}</div>
        <button 
          onClick={loadHistory}
          className="mt-2 text-blue-600 hover:text-blue-800 text-sm"
        >
          Réessayer
        </button>
      </div>
    );
  }

  return (
    <div className={`bg-white rounded-lg shadow-md p-4 ${className}`}>
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-lg font-semibold text-gray-800">Historique des confirmations</h3>
        <button 
          onClick={loadHistory}
          className="text-blue-600 hover:text-blue-800 text-sm"
        >
          Actualiser
        </button>
      </div>

      {history.length === 0 ? (
        <div className="text-gray-500 text-center py-8">
          <Clock size={48} className="mx-auto mb-2 text-gray-300" />
          <p>Aucune confirmation enregistrée</p>
        </div>
      ) : (
        <div className="space-y-3 max-h-96 overflow-y-auto">
          {history.map((record) => {
            const correctionType = getCorrectionType(record);
            const { date, time } = formatDateTime(record.created_at);
            
            return (
              <div 
                key={record.id} 
                className={`
                  p-3 rounded-lg border-l-4 transition-all
                  ${correctionType === 'correction' 
                    ? 'bg-orange-50 border-orange-400' 
                    : 'bg-green-50 border-green-400'
                  }
                `}
              >
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    {/* Action header */}
                    <div className="flex items-center gap-2 mb-2">
                      {correctionType === 'correction' ? (
                        <span className="text-orange-700 font-medium text-sm">
                          Correction
                        </span>
                      ) : (
                        <span className="text-green-700 font-medium text-sm">
                          Confirmation
                        </span>
                      )}
                      <div className="flex items-center gap-1 text-xs text-gray-500">
                        <Clock size={12} />
                        <span>{date} à {time}</span>
                      </div>
                    </div>

                    {/* State change */}
                    <div className="flex items-center gap-2 mb-2">
                      <div className="flex items-center gap-1">
                        {getStateIcon(record.previous_state)}
                        <span className="text-sm text-gray-600 capitalize">
                          {record.previous_state === 'opened' ? 'Ouverte' : 'Fermée'}
                        </span>
                        {getCertaintyIcon(record.previous_certainty)}
                      </div>
                      <span className="text-gray-400">→</span>
                      <div className="flex items-center gap-1">
                        {getStateIcon(record.confirmed_state)}
                        <span className="text-sm text-gray-800 font-medium capitalize">
                          {record.confirmed_state === 'opened' ? 'Ouverte' : 'Fermée'}
                        </span>
                        <CheckCircle size={14} className="text-green-500" />
                      </div>
                    </div>

                    {/* User and notes */}
                    <div className="flex items-center gap-2 text-xs text-gray-500">
                      <User size={12} />
                      <span>{record.user.name}</span>
                    </div>

                    {record.user_notes && (
                      <div className="mt-2 text-sm text-gray-600 italic">
                        "{record.user_notes}"
                      </div>
                    )}
                  </div>

                  {/* Position indicator */}
                  {record.sensor_position && (
                    <div className="text-xs text-gray-400 font-mono">
                      <div>X: {record.sensor_position.x.toFixed(1)}</div>
                      <div>Y: {record.sensor_position.y.toFixed(1)}</div>
                      <div>Z: {record.sensor_position.z.toFixed(1)}</div>
                    </div>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
};

export default DoorStateHistory;