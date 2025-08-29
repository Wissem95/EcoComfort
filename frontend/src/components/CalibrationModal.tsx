import React, { useState, useEffect } from 'react'
import { X, Settings, CheckCircle, AlertTriangle, Loader2, Activity } from 'lucide-react'
import apiService from '../services/api'

interface CalibrationModalProps {
  isOpen: boolean
  onClose: () => void
  sensor: {
    sensor_id: string
    name: string
    room: {
      name: string
      building_name: string
    }
  }
}

interface CalibrationStatus {
  calibrated: boolean
  current_values?: {
    x: number
    y: number
    z: number
  } | null
  message?: string
}

interface StabilityData {
  stable: boolean
  current_values?: {
    x: number
    y: number
    z: number
  } | null
  stability_metrics?: {
    variance_x: number
    variance_y: number
    variance_z: number
    overall_stability: number
    sample_count: number
  }
  ready_for_calibration: boolean
  reason?: string
}

interface CalibrationResult {
  success: boolean
  message: string
  calibration?: {
    closed_reference: {
      x: number
      y: number
      z: number
    }
    confidence: number
    data_stability: number
  }
  error?: string
}

const CalibrationModal: React.FC<CalibrationModalProps> = ({
  isOpen,
  onClose,
  sensor
}) => {
  const [step, setStep] = useState<'checking' | 'instructions' | 'calibrating' | 'result'>('checking')
  const [stabilityData, setStabilityData] = useState<StabilityData | null>(null)
  const [calibrationStatus, setCalibrationStatus] = useState<CalibrationStatus | null>(null)
  const [calibrationResult, setCalibrationResult] = useState<CalibrationResult | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (isOpen && sensor) {
      checkInitialState()
    }
  }, [isOpen, sensor?.sensor_id]) // eslint-disable-line react-hooks/exhaustive-deps

  const checkInitialState = async () => {
    setStep('checking')
    setError(null)
    setLoading(true)

    try {
      // Check current calibration status
      const status = await apiService.getCalibrationStatus(sensor.sensor_id)
      setCalibrationStatus(status)

      // Check stability
      await checkStability()
    } catch {
      setError('Erreur lors de la vérification du statut du capteur')
    } finally {
      setLoading(false)
    }
  }

  const checkStability = async () => {
    try {
      const stability = await apiService.checkSensorStability(sensor.sensor_id)
      setStabilityData(stability)
      
      if (stability.ready_for_calibration) {
        setStep('instructions')
      } else {
        setStep('checking')
      }
    } catch {
      setError('Erreur lors de la vérification de la stabilité')
    }
  }

  const handleCalibrate = async () => {
    setStep('calibrating')
    setLoading(true)
    setError(null)

    try {
      const result = await apiService.calibrateDoorPosition(sensor.sensor_id, {
        type: 'closed_position',
        confirm: true,
        override_existing: calibrationStatus?.calibrated || false
      })

      if (result.success) {
        setCalibrationResult(result)
        setStep('result')
      } else {
        setError(result.message || 'Erreur lors de la calibration')
        setStep('instructions')
      }
    } catch {
      setError('Erreur lors de la calibration')
      setStep('instructions')
    } finally {
      setLoading(false)
    }
  }

  const handleClose = () => {
    setStep('checking')
    setStabilityData(null)
    setCalibrationStatus(null)
    setCalibrationResult(null)
    setError(null)
    onClose()
  }

  if (!isOpen || !sensor) return null

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div className="bg-gray-800 rounded-lg max-w-md w-full p-6 relative">
        <button
          onClick={handleClose}
          className="absolute top-4 right-4 text-gray-400 hover:text-white"
        >
          <X className="w-6 h-6" />
        </button>

        <div className="flex items-center gap-3 mb-6">
          <Settings className="w-6 h-6 text-blue-400" />
          <div>
            <h3 className="text-xl font-semibold text-white">Calibration Capteur</h3>
            <p className="text-sm text-gray-400">{sensor.name}</p>
            <p className="text-xs text-gray-500">{sensor.room.name} - {sensor.room.building_name}</p>
          </div>
        </div>

        {/* Checking Step */}
        {step === 'checking' && (
          <div className="text-center py-8">
            {loading ? (
              <div className="space-y-4">
                <Loader2 className="w-8 h-8 animate-spin mx-auto text-blue-400" />
                <p className="text-white">Vérification du capteur...</p>
              </div>
            ) : (
              <div className="space-y-4">
                {stabilityData && !stabilityData.ready_for_calibration ? (
                  <div className="space-y-3">
                    <AlertTriangle className="w-8 h-8 mx-auto text-yellow-400" />
                    <p className="text-white">Capteur non stable</p>
                    <p className="text-sm text-gray-400">{stabilityData.reason}</p>
                    {stabilityData.current_values && (
                      <div className="bg-gray-700 rounded p-3 text-sm">
                        <p className="text-gray-300">Valeurs actuelles :</p>
                        <div className="grid grid-cols-3 gap-2 mt-2">
                          <div className="text-center">
                            <p className="text-xs text-gray-400">X</p>
                            <p className="text-white">{stabilityData.current_values.x}</p>
                          </div>
                          <div className="text-center">
                            <p className="text-xs text-gray-400">Y</p>
                            <p className="text-white">{stabilityData.current_values.y}</p>
                          </div>
                          <div className="text-center">
                            <p className="text-xs text-gray-400">Z</p>
                            <p className="text-white">{stabilityData.current_values.z}</p>
                          </div>
                        </div>
                      </div>
                    )}
                    <button
                      onClick={checkStability}
                      className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
                    >
                      <Activity className="w-4 h-4 inline mr-2" />
                      Revérifier
                    </button>
                  </div>
                ) : error && (
                  <div className="space-y-3">
                    <AlertTriangle className="w-8 h-8 mx-auto text-red-400" />
                    <p className="text-white">Erreur</p>
                    <p className="text-sm text-gray-400">{error}</p>
                    <button
                      onClick={checkInitialState}
                      className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
                    >
                      Réessayer
                    </button>
                  </div>
                )}
              </div>
            )}
          </div>
        )}

        {/* Instructions Step */}
        {step === 'instructions' && (
          <div className="space-y-6">
            <div className="bg-blue-900/30 border border-blue-500/30 rounded-lg p-4">
              <h4 className="text-blue-300 font-medium mb-2">Instructions</h4>
              <div className="space-y-2 text-sm text-blue-200">
                <p>1. Assurez-vous que la porte est complètement fermée</p>
                <p>2. Attendez que le capteur soit stable (ne bougez pas la porte)</p>
                <p>3. Cliquez sur "Calibrer" pour enregistrer la position fermée</p>
              </div>
            </div>

            {calibrationStatus?.calibrated && (
              <div className="bg-yellow-900/30 border border-yellow-500/30 rounded-lg p-3">
                <p className="text-yellow-200 text-sm">
                  ⚠️ Ce capteur est déjà calibré. Cette action remplacera la calibration existante.
                </p>
              </div>
            )}

            {stabilityData?.current_values && (
              <div className="bg-gray-700 rounded-lg p-3">
                <p className="text-white text-sm mb-2">Position actuelle :</p>
                <div className="grid grid-cols-3 gap-3">
                  <div className="text-center">
                    <p className="text-xs text-gray-400">X</p>
                    <p className="text-white font-mono">{stabilityData.current_values.x}</p>
                  </div>
                  <div className="text-center">
                    <p className="text-xs text-gray-400">Y</p>
                    <p className="text-white font-mono">{stabilityData.current_values.y}</p>
                  </div>
                  <div className="text-center">
                    <p className="text-xs text-gray-400">Z</p>
                    <p className="text-white font-mono">{stabilityData.current_values.z}</p>
                  </div>
                </div>
                {stabilityData.stability_metrics && (
                  <div className="mt-2 text-center">
                    <p className="text-xs text-gray-400">Stabilité</p>
                    <p className="text-green-400 text-sm font-medium">
                      {Math.round(stabilityData.stability_metrics.overall_stability * 100)}%
                    </p>
                  </div>
                )}
              </div>
            )}

            {error && (
              <div className="bg-red-900/30 border border-red-500/30 rounded-lg p-3">
                <p className="text-red-200 text-sm">{error}</p>
              </div>
            )}

            <div className="flex gap-3">
              <button
                onClick={handleClose}
                className="flex-1 px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors"
              >
                Annuler
              </button>
              <button
                onClick={handleCalibrate}
                disabled={!stabilityData?.ready_for_calibration}
                className="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 disabled:opacity-50 text-white rounded-lg transition-colors"
              >
                <Settings className="w-4 h-4 inline mr-2" />
                Calibrer
              </button>
            </div>
          </div>
        )}

        {/* Calibrating Step */}
        {step === 'calibrating' && (
          <div className="text-center py-8">
            <Loader2 className="w-8 h-8 animate-spin mx-auto text-green-400 mb-4" />
            <p className="text-white mb-2">Calibration en cours...</p>
            <p className="text-sm text-gray-400">Veuillez patienter</p>
          </div>
        )}

        {/* Result Step */}
        {step === 'result' && calibrationResult && (
          <div className="space-y-4">
            {calibrationResult.success ? (
              <div className="text-center py-4">
                <CheckCircle className="w-12 h-12 mx-auto text-green-400 mb-4" />
                <h4 className="text-xl font-semibold text-white mb-2">Calibration Réussie !</h4>
                <p className="text-green-400 mb-4">{calibrationResult.message}</p>
                
                {calibrationResult.calibration && (
                  <div className="bg-green-900/20 border border-green-500/30 rounded-lg p-4 text-left">
                    <p className="text-green-300 font-medium mb-3">Position de référence enregistrée :</p>
                    <div className="grid grid-cols-3 gap-3 mb-3">
                      <div className="text-center">
                        <p className="text-xs text-gray-400">X</p>
                        <p className="text-white font-mono">{calibrationResult.calibration.closed_reference.x}</p>
                      </div>
                      <div className="text-center">
                        <p className="text-xs text-gray-400">Y</p>
                        <p className="text-white font-mono">{calibrationResult.calibration.closed_reference.y}</p>
                      </div>
                      <div className="text-center">
                        <p className="text-xs text-gray-400">Z</p>
                        <p className="text-white font-mono">{calibrationResult.calibration.closed_reference.z}</p>
                      </div>
                    </div>
                    <div className="text-center">
                      <p className="text-xs text-gray-400">Indice de confiance</p>
                      <p className="text-green-400 font-semibold">
                        {Math.round(calibrationResult.calibration.confidence * 100)}%
                      </p>
                    </div>
                  </div>
                )}
              </div>
            ) : (
              <div className="text-center py-4">
                <AlertTriangle className="w-12 h-12 mx-auto text-red-400 mb-4" />
                <h4 className="text-xl font-semibold text-white mb-2">Calibration Échouée</h4>
                <p className="text-red-400">{calibrationResult.message}</p>
              </div>
            )}

            <button
              onClick={handleClose}
              className="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
            >
              Fermer
            </button>
          </div>
        )}
      </div>
    </div>
  )
}

export default CalibrationModal