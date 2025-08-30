import React, { useState } from 'react';
import { DoorOpen, DoorClosed, RotateCcw, AlertTriangle, Edit } from 'lucide-react';

interface DoorStateIndicatorProps {
  state: 'closed' | 'opened' | 'probably_opened' | 'moving';
  certainty: 'CERTAIN' | 'PROBABLE' | 'UNCERTAIN';
  needsConfirmation: boolean;
  sensorId: string;
  onConfirmState?: (sensorId: string, state: 'closed' | 'opened', notes?: string) => void;
  className?: string;
}

const DoorStateIndicator: React.FC<DoorStateIndicatorProps> = ({
  state,
  certainty,
  needsConfirmation,
  sensorId,
  onConfirmState,
  className = ''
}) => {
  const [showConfirmModal, setShowConfirmModal] = useState(false);

  const stateConfig = {
    closed: {
      label: 'FERMÉE',
      icon: DoorClosed,
      color: '#10B981', // Vert
      bgColor: 'bg-green-100',
      textColor: 'text-green-800',
      borderColor: 'border-green-200'
    },
    opened: {
      label: 'OUVERTE', 
      icon: DoorOpen,
      color: '#EF4444', // Rouge
      bgColor: 'bg-red-100',
      textColor: 'text-red-800',
      borderColor: 'border-red-200'
    },
    probably_opened: {
      label: 'PROBABLEMENT OUVERTE',
      icon: AlertTriangle,
      color: '#F59E0B', // Orange
      bgColor: 'bg-orange-100', 
      textColor: 'text-orange-800',
      borderColor: 'border-orange-200'
    },
    moving: {
      label: 'EN MOUVEMENT',
      icon: RotateCcw,
      color: '#3B82F6', // Bleu
      bgColor: 'bg-blue-100',
      textColor: 'text-blue-800', 
      borderColor: 'border-blue-200'
    }
  };

  const config = stateConfig[state];
  const IconComponent = config.icon;

  const certaintyIndicator = {
    CERTAIN: '✅',
    PROBABLE: '⚠️', 
    UNCERTAIN: '❓'
  };

  return (
    <div className={`relative ${className}`}>
      <div className="flex items-center gap-1">
        <div
          onClick={() => setShowConfirmModal(true)}
          className={`
            flex items-center gap-2 px-3 py-2 rounded-lg border-2 transition-all duration-200 cursor-pointer hover:opacity-80
            ${config.bgColor} ${config.textColor} ${config.borderColor}
            ${needsConfirmation ? 'ring-2 ring-orange-300 ring-opacity-50 animate-pulse' : ''}
          `}
          style={{ borderColor: config.color }}
          title="Cliquez pour corriger l'état"
        >
          <IconComponent 
            size={18} 
            color={config.color}
            className={state === 'moving' ? 'animate-spin' : ''}
          />
          <span className="font-medium text-sm">
            {config.label}
          </span>
          <span className="text-xs opacity-75">
            {certaintyIndicator[certainty]}
          </span>
        </div>
        
        <button
          onClick={() => setShowConfirmModal(true)}
          className="
            p-1 text-gray-400 hover:text-gray-600 transition-colors duration-200 
            hover:bg-gray-100 rounded-md
          "
          title="Modifier l'état manuellement"
        >
          <Edit size={14} />
        </button>
      </div>

      {needsConfirmation && (
        <button
          onClick={() => setShowConfirmModal(true)}
          className="
            absolute -top-2 -right-2 bg-orange-500 text-white text-xs px-2 py-1 
            rounded-full shadow-lg hover:bg-orange-600 transition-colors duration-200
            animate-bounce
          "
        >
          Confirmer
        </button>
      )}

      {showConfirmModal && (
        <ConfirmationModal
          currentState={state}
          isConfirmationRequired={needsConfirmation}
          onConfirm={(confirmedState, notes) => {
            onConfirmState?.(sensorId, confirmedState, notes);
            setShowConfirmModal(false);
          }}
          onClose={() => setShowConfirmModal(false)}
        />
      )}
    </div>
  );
};

interface ConfirmationModalProps {
  currentState: string;
  isConfirmationRequired: boolean;
  onConfirm: (state: 'closed' | 'opened', notes?: string) => void;
  onClose: () => void;
}

const ConfirmationModal: React.FC<ConfirmationModalProps> = ({
  currentState,
  isConfirmationRequired,
  onConfirm,
  onClose
}) => {
  // Pre-select current state, defaulting to 'closed' if current state is not determinable
  const getInitialState = (): 'closed' | 'opened' => {
    if (currentState === 'opened' || currentState === 'probably_opened') return 'opened';
    return 'closed';
  };
  
  const [selectedState, setSelectedState] = useState<'closed' | 'opened'>(getInitialState());
  const [notes, setNotes] = useState('');

  const modalTitle = isConfirmationRequired 
    ? "Confirmer l'état de la porte" 
    : "Modifier l'état de la porte";

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg p-6 w-96 shadow-xl">
        <h3 className="text-lg font-semibold mb-4 text-gray-800">
          {modalTitle}
        </h3>
        
        <p className="text-sm text-gray-600 mb-4">
          État détecté : <span className="font-medium">{currentState}</span>
        </p>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-2">
            État réel :
          </label>
          <div className="space-y-2">
            <label className="flex items-center">
              <input
                type="radio"
                value="closed"
                checked={selectedState === 'closed'}
                onChange={(e) => setSelectedState(e.target.value as 'closed')}
                className="mr-2"
              />
              <DoorClosed size={16} className="mr-2 text-green-600" />
              <span>Fermée</span>
            </label>
            <label className="flex items-center">
              <input
                type="radio"
                value="opened"
                checked={selectedState === 'opened'}
                onChange={(e) => setSelectedState(e.target.value as 'opened')}
                className="mr-2"
              />
              <DoorOpen size={16} className="mr-2 text-red-600" />
              <span>Ouverte</span>
            </label>
          </div>
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Notes (optionnel) :
          </label>
          <textarea
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
            rows={3}
            placeholder="Commentaires supplémentaires..."
            maxLength={500}
          />
        </div>

        <div className="flex gap-3 justify-end">
          <button
            onClick={onClose}
            className="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50"
          >
            Annuler
          </button>
          <button
            onClick={() => onConfirm(selectedState, notes)}
            className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700"
          >
            Confirmer
          </button>
        </div>
      </div>
    </div>
  );
};

export default DoorStateIndicator;