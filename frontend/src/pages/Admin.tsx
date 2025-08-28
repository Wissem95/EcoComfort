import React, { useState, useEffect } from 'react';
import { Plus, Edit2, Trash2, Building, DoorOpen, Wifi, Save, X } from 'lucide-react';
import apiService from '../services/api';

interface BuildingData {
  id: string;
  name: string;
  address: string;
  rooms?: RoomData[];
}

interface RoomData {
  id: string;
  name: string;
  building_id: string;
  building?: BuildingData;
  floor: number;
  surface_m2: number;
  sensors?: SensorData[];
}

interface SensorData {
  id: string;
  name: string;
  room_id: string;
  room?: RoomData;
  type: string;
  mqtt_topic: string;
  position: string;
  mac_address?: string;
  is_active: boolean;
  battery_level?: number;
}

const Admin: React.FC = () => {
  const [activeTab, setActiveTab] = useState<'buildings' | 'rooms' | 'sensors'>('buildings');
  const [buildings, setBuildings] = useState<BuildingData[]>([]);
  const [rooms, setRooms] = useState<RoomData[]>([]);
  const [sensors, setSensors] = useState<SensorData[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Form states
  const [showBuildingForm, setShowBuildingForm] = useState(false);
  const [showRoomForm, setShowRoomForm] = useState(false);
  const [showSensorForm, setShowSensorForm] = useState(false);
  
  const [buildingForm, setBuildingForm] = useState({ name: '', address: '' });
  const [roomForm, setRoomForm] = useState({ name: '', building_id: '', floor: 0, surface_m2: 0 });
  const [sensorForm, setSensorForm] = useState({
    name: '',
    room_id: '',
    type: 'ruuvitag',
    mqtt_topic: '',
    position: '',
    mac_address: ''
  });

  const [editingId, setEditingId] = useState<string | null>(null);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    setLoading(true);
    setError(null);
    try {
      const [buildingsRes, roomsRes, sensorsRes] = await Promise.all([
        fetch('/api/admin/buildings', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Content-Type': 'application/json'
          }
        }).then(r => r.json()),
        fetch('/api/admin/rooms', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Content-Type': 'application/json'
          }
        }).then(r => r.json()),
        fetch('/api/admin/sensors', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Content-Type': 'application/json'
          }
        }).then(r => r.json())
      ]);

      if (buildingsRes.success) setBuildings(buildingsRes.data);
      if (roomsRes.success) setRooms(roomsRes.data);
      if (sensorsRes.success) setSensors(sensorsRes.data);
    } catch (err) {
      setError('Erreur lors du chargement des données');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  // Buildings CRUD
  const saveBuilding = async () => {
    try {
      const method = editingId ? 'PUT' : 'POST';
      const url = editingId ? `/api/admin/buildings/${editingId}` : '/api/admin/buildings';
      
      const response = await fetch(url, {
        method,
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(buildingForm)
      });

      const data = await response.json();
      if (data.success) {
        await loadData();
        setShowBuildingForm(false);
        setBuildingForm({ name: '', address: '' });
        setEditingId(null);
      }
    } catch (err) {
      setError('Erreur lors de la sauvegarde');
    }
  };

  const deleteBuilding = async (id: string) => {
    if (!confirm('Supprimer ce bâtiment ?')) return;
    
    try {
      const response = await fetch(`/api/admin/buildings/${id}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Content-Type': 'application/json'
        }
      });

      const data = await response.json();
      if (data.success) {
        await loadData();
      } else {
        alert(data.message || 'Erreur lors de la suppression');
      }
    } catch (err) {
      setError('Erreur lors de la suppression');
    }
  };

  // Rooms CRUD
  const saveRoom = async () => {
    try {
      const method = editingId ? 'PUT' : 'POST';
      const url = editingId ? `/api/admin/rooms/${editingId}` : '/api/admin/rooms';
      
      const response = await fetch(url, {
        method,
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          ...roomForm,
          floor: parseInt(roomForm.floor.toString()),
          surface_m2: parseFloat(roomForm.surface_m2.toString())
        })
      });

      const data = await response.json();
      if (data.success) {
        await loadData();
        setShowRoomForm(false);
        setRoomForm({ name: '', building_id: '', floor: 0, surface_m2: 0 });
        setEditingId(null);
      }
    } catch (err) {
      setError('Erreur lors de la sauvegarde');
    }
  };

  const deleteRoom = async (id: string) => {
    if (!confirm('Supprimer cette salle ?')) return;
    
    try {
      const response = await fetch(`/api/admin/rooms/${id}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Content-Type': 'application/json'
        }
      });

      const data = await response.json();
      if (data.success) {
        await loadData();
      } else {
        alert(data.message || 'Erreur lors de la suppression');
      }
    } catch (err) {
      setError('Erreur lors de la suppression');
    }
  };

  // Sensors CRUD
  const saveSensor = async () => {
    try {
      const method = editingId ? 'PUT' : 'POST';
      const url = editingId ? `/api/admin/sensors/${editingId}` : '/api/admin/sensors';
      
      // Pour un RuuviTag unique, utiliser le topic correspondant au type de donnée
      const mqttTopic = sensorForm.mqtt_topic || (
        sensorForm.type === 'temperature' ? '112' :
        sensorForm.type === 'humidity' ? '114' :
        sensorForm.type === 'movement' ? '127' : '112'
      );
      
      const response = await fetch(url, {
        method,
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          ...sensorForm,
          mqtt_topic: mqttTopic
        })
      });

      const data = await response.json();
      if (data.success) {
        await loadData();
        setShowSensorForm(false);
        setSensorForm({
          name: '',
          room_id: '',
          type: 'ruuvitag',
          mqtt_topic: '',
          position: '',
          mac_address: ''
        });
        setEditingId(null);
      }
    } catch (err) {
      setError('Erreur lors de la sauvegarde');
    }
  };

  const deleteSensor = async (id: string) => {
    if (!confirm('Désactiver ce capteur ?')) return;
    
    try {
      const response = await fetch(`/api/admin/sensors/${id}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Content-Type': 'application/json'
        }
      });

      const data = await response.json();
      if (data.success) {
        await loadData();
      }
    } catch (err) {
      setError('Erreur lors de la désactivation');
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 p-4">
      <div className="max-w-7xl mx-auto">
        <h1 className="text-3xl font-bold text-white mb-8">Administration</h1>
        
        {/* Tabs */}
        <div className="flex space-x-1 mb-6 bg-white/10 backdrop-blur-md rounded-xl p-1">
          <button
            onClick={() => setActiveTab('buildings')}
            className={`flex-1 px-4 py-2 rounded-lg transition-all ${
              activeTab === 'buildings'
                ? 'bg-gradient-to-r from-purple-500 to-pink-500 text-white'
                : 'text-white/70 hover:text-white'
            }`}
          >
            <Building className="w-5 h-5 inline-block mr-2" />
            Bâtiments
          </button>
          <button
            onClick={() => setActiveTab('rooms')}
            className={`flex-1 px-4 py-2 rounded-lg transition-all ${
              activeTab === 'rooms'
                ? 'bg-gradient-to-r from-purple-500 to-pink-500 text-white'
                : 'text-white/70 hover:text-white'
            }`}
          >
            <DoorOpen className="w-5 h-5 inline-block mr-2" />
            Salles
          </button>
          <button
            onClick={() => setActiveTab('sensors')}
            className={`flex-1 px-4 py-2 rounded-lg transition-all ${
              activeTab === 'sensors'
                ? 'bg-gradient-to-r from-purple-500 to-pink-500 text-white'
                : 'text-white/70 hover:text-white'
            }`}
          >
            <Wifi className="w-5 h-5 inline-block mr-2" />
            Capteurs
          </button>
        </div>

        {error && (
          <div className="bg-red-500/20 border border-red-500 text-white p-4 rounded-xl mb-4">
            {error}
          </div>
        )}

        {loading && (
          <div className="text-center text-white py-8">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-white mx-auto"></div>
            <p className="mt-4">Chargement...</p>
          </div>
        )}

        {/* Buildings Tab */}
        {activeTab === 'buildings' && !loading && (
          <div>
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-xl font-semibold text-white">Bâtiments ({buildings.length})</h2>
              <button
                onClick={() => {
                  setShowBuildingForm(true);
                  setEditingId(null);
                  setBuildingForm({ name: '', address: '' });
                }}
                className="bg-gradient-to-r from-green-500 to-emerald-500 text-white px-4 py-2 rounded-lg flex items-center hover:opacity-90"
              >
                <Plus className="w-5 h-5 mr-2" />
                Ajouter
              </button>
            </div>

            {showBuildingForm && (
              <div className="bg-white/10 backdrop-blur-md rounded-xl p-6 mb-4">
                <h3 className="text-lg font-semibold text-white mb-4">
                  {editingId ? 'Modifier' : 'Ajouter'} un bâtiment
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <input
                    type="text"
                    placeholder="Nom du bâtiment"
                    value={buildingForm.name}
                    onChange={(e) => setBuildingForm({ ...buildingForm, name: e.target.value })}
                    className="bg-white/20 text-white placeholder-white/50 px-4 py-2 rounded-lg"
                  />
                  <input
                    type="text"
                    placeholder="Adresse"
                    value={buildingForm.address}
                    onChange={(e) => setBuildingForm({ ...buildingForm, address: e.target.value })}
                    className="bg-white/20 text-white placeholder-white/50 px-4 py-2 rounded-lg"
                  />
                </div>
                <div className="flex justify-end space-x-2 mt-4">
                  <button
                    onClick={() => setShowBuildingForm(false)}
                    className="bg-gray-500 text-white px-4 py-2 rounded-lg hover:opacity-90"
                  >
                    <X className="w-5 h-5 inline-block mr-2" />
                    Annuler
                  </button>
                  <button
                    onClick={saveBuilding}
                    className="bg-gradient-to-r from-green-500 to-emerald-500 text-white px-4 py-2 rounded-lg hover:opacity-90"
                  >
                    <Save className="w-5 h-5 inline-block mr-2" />
                    Enregistrer
                  </button>
                </div>
              </div>
            )}

            <div className="space-y-2">
              {buildings.map(building => (
                <div key={building.id} className="bg-white/10 backdrop-blur-md rounded-xl p-4 flex justify-between items-center">
                  <div>
                    <h3 className="font-semibold text-white">{building.name}</h3>
                    <p className="text-white/70 text-sm">{building.address}</p>
                    <p className="text-white/50 text-xs">{building.rooms?.length || 0} salles</p>
                  </div>
                  <div className="flex space-x-2">
                    <button
                      onClick={() => {
                        setEditingId(building.id);
                        setBuildingForm({ name: building.name, address: building.address });
                        setShowBuildingForm(true);
                      }}
                      className="text-blue-400 hover:text-blue-300"
                    >
                      <Edit2 className="w-5 h-5" />
                    </button>
                    <button
                      onClick={() => deleteBuilding(building.id)}
                      className="text-red-400 hover:text-red-300"
                    >
                      <Trash2 className="w-5 h-5" />
                    </button>
                  </div>
                </div>
              ))}
              
              {buildings.length === 0 && (
                <div className="text-center py-8 text-white/50">
                  Aucun bâtiment. Ajoutez votre premier bâtiment.
                </div>
              )}
            </div>
          </div>
        )}

        {/* Rooms Tab */}
        {activeTab === 'rooms' && !loading && (
          <div>
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-xl font-semibold text-white">Salles ({rooms.length})</h2>
              <button
                onClick={() => {
                  setShowRoomForm(true);
                  setEditingId(null);
                  setRoomForm({ name: '', building_id: '', floor: 0, surface_m2: 0 });
                }}
                className="bg-gradient-to-r from-green-500 to-emerald-500 text-white px-4 py-2 rounded-lg flex items-center hover:opacity-90"
                disabled={buildings.length === 0}
              >
                <Plus className="w-5 h-5 mr-2" />
                Ajouter
              </button>
            </div>

            {showRoomForm && (
              <div className="bg-white/10 backdrop-blur-md rounded-xl p-6 mb-4">
                <h3 className="text-lg font-semibold text-white mb-4">
                  {editingId ? 'Modifier' : 'Ajouter'} une salle
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <input
                    type="text"
                    placeholder="Nom de la salle"
                    value={roomForm.name}
                    onChange={(e) => setRoomForm({ ...roomForm, name: e.target.value })}
                    className="bg-white/20 text-white placeholder-white/50 px-4 py-2 rounded-lg"
                  />
                  <select
                    value={roomForm.building_id}
                    onChange={(e) => setRoomForm({ ...roomForm, building_id: e.target.value })}
                    className="bg-white/20 text-white px-4 py-2 rounded-lg"
                  >
                    <option value="">Sélectionner un bâtiment</option>
                    {buildings.map(b => (
                      <option key={b.id} value={b.id}>{b.name}</option>
                    ))}
                  </select>
                  <input
                    type="number"
                    placeholder="Étage"
                    value={roomForm.floor}
                    onChange={(e) => setRoomForm({ ...roomForm, floor: parseInt(e.target.value) })}
                    className="bg-white/20 text-white placeholder-white/50 px-4 py-2 rounded-lg"
                  />
                  <input
                    type="number"
                    placeholder="Surface (m²)"
                    value={roomForm.surface_m2}
                    onChange={(e) => setRoomForm({ ...roomForm, surface_m2: parseFloat(e.target.value) })}
                    className="bg-white/20 text-white placeholder-white/50 px-4 py-2 rounded-lg"
                  />
                </div>
                <div className="flex justify-end space-x-2 mt-4">
                  <button
                    onClick={() => setShowRoomForm(false)}
                    className="bg-gray-500 text-white px-4 py-2 rounded-lg hover:opacity-90"
                  >
                    <X className="w-5 h-5 inline-block mr-2" />
                    Annuler
                  </button>
                  <button
                    onClick={saveRoom}
                    className="bg-gradient-to-r from-green-500 to-emerald-500 text-white px-4 py-2 rounded-lg hover:opacity-90"
                  >
                    <Save className="w-5 h-5 inline-block mr-2" />
                    Enregistrer
                  </button>
                </div>
              </div>
            )}

            <div className="space-y-2">
              {rooms.map(room => (
                <div key={room.id} className="bg-white/10 backdrop-blur-md rounded-xl p-4 flex justify-between items-center">
                  <div>
                    <h3 className="font-semibold text-white">{room.name}</h3>
                    <p className="text-white/70 text-sm">{room.building?.name} - Étage {room.floor}</p>
                    <p className="text-white/50 text-xs">{room.surface_m2} m² - {room.sensors?.length || 0} capteurs</p>
                  </div>
                  <div className="flex space-x-2">
                    <button
                      onClick={() => {
                        setEditingId(room.id);
                        setRoomForm({
                          name: room.name,
                          building_id: room.building_id,
                          floor: room.floor,
                          surface_m2: room.surface_m2
                        });
                        setShowRoomForm(true);
                      }}
                      className="text-blue-400 hover:text-blue-300"
                    >
                      <Edit2 className="w-5 h-5" />
                    </button>
                    <button
                      onClick={() => deleteRoom(room.id)}
                      className="text-red-400 hover:text-red-300"
                    >
                      <Trash2 className="w-5 h-5" />
                    </button>
                  </div>
                </div>
              ))}
              
              {rooms.length === 0 && (
                <div className="text-center py-8 text-white/50">
                  {buildings.length === 0
                    ? 'Ajoutez d\'abord un bâtiment'
                    : 'Aucune salle. Ajoutez votre première salle.'}
                </div>
              )}
            </div>
          </div>
        )}

        {/* Sensors Tab */}
        {activeTab === 'sensors' && !loading && (
          <div>
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-xl font-semibold text-white">Capteurs ({sensors.filter(s => s.is_active).length} actifs)</h2>
              <button
                onClick={() => {
                  setShowSensorForm(true);
                  setEditingId(null);
                  setSensorForm({
                    name: '',
                    room_id: '',
                    type: 'ruuvitag',
                    mqtt_topic: '',
                    position: '',
                    mac_address: ''
                  });
                }}
                className="bg-gradient-to-r from-green-500 to-emerald-500 text-white px-4 py-2 rounded-lg flex items-center hover:opacity-90"
                disabled={rooms.length === 0}
              >
                <Plus className="w-5 h-5 mr-2" />
                Ajouter
              </button>
            </div>

            {showSensorForm && (
              <div className="bg-white/10 backdrop-blur-md rounded-xl p-6 mb-4">
                <h3 className="text-lg font-semibold text-white mb-4">
                  {editingId ? 'Modifier' : 'Ajouter'} un capteur RuuviTag
                </h3>
                <div className="bg-blue-500/20 border border-blue-500 text-white p-3 rounded-lg mb-4">
                  <p className="text-sm">Configuration pour 1 seul RuuviTag physique</p>
                  <p className="text-xs text-white/70">Topics MQTT : 112 (temp), 114 (humidité), 127 (mouvement)</p>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <input
                    type="text"
                    placeholder="Nom du capteur"
                    value={sensorForm.name}
                    onChange={(e) => setSensorForm({ ...sensorForm, name: e.target.value })}
                    className="bg-white/20 text-white placeholder-white/50 px-4 py-2 rounded-lg"
                  />
                  <select
                    value={sensorForm.room_id}
                    onChange={(e) => setSensorForm({ ...sensorForm, room_id: e.target.value })}
                    className="bg-white/20 text-white px-4 py-2 rounded-lg"
                  >
                    <option value="">Sélectionner une salle</option>
                    {rooms.map(r => (
                      <option key={r.id} value={r.id}>
                        {r.name} ({r.building?.name})
                      </option>
                    ))}
                  </select>
                  <select
                    value={sensorForm.type}
                    onChange={(e) => setSensorForm({ ...sensorForm, type: e.target.value })}
                    className="bg-white/20 text-white px-4 py-2 rounded-lg"
                  >
                    <option value="ruuvitag">RuuviTag Complet</option>
                    <option value="temperature">Température (112)</option>
                    <option value="humidity">Humidité (114)</option>
                    <option value="movement">Mouvement (127)</option>
                  </select>
                  <input
                    type="text"
                    placeholder="Topic MQTT (auto si vide)"
                    value={sensorForm.mqtt_topic}
                    onChange={(e) => setSensorForm({ ...sensorForm, mqtt_topic: e.target.value })}
                    className="bg-white/20 text-white placeholder-white/50 px-4 py-2 rounded-lg"
                  />
                  <input
                    type="text"
                    placeholder="Position (ex: mur-nord)"
                    value={sensorForm.position}
                    onChange={(e) => setSensorForm({ ...sensorForm, position: e.target.value })}
                    className="bg-white/20 text-white placeholder-white/50 px-4 py-2 rounded-lg"
                  />
                  <input
                    type="text"
                    placeholder="Adresse MAC (optionnel)"
                    value={sensorForm.mac_address}
                    onChange={(e) => setSensorForm({ ...sensorForm, mac_address: e.target.value })}
                    className="bg-white/20 text-white placeholder-white/50 px-4 py-2 rounded-lg"
                  />
                </div>
                <div className="flex justify-end space-x-2 mt-4">
                  <button
                    onClick={() => setShowSensorForm(false)}
                    className="bg-gray-500 text-white px-4 py-2 rounded-lg hover:opacity-90"
                  >
                    <X className="w-5 h-5 inline-block mr-2" />
                    Annuler
                  </button>
                  <button
                    onClick={saveSensor}
                    className="bg-gradient-to-r from-green-500 to-emerald-500 text-white px-4 py-2 rounded-lg hover:opacity-90"
                  >
                    <Save className="w-5 h-5 inline-block mr-2" />
                    Enregistrer
                  </button>
                </div>
              </div>
            )}

            <div className="space-y-2">
              {sensors.map(sensor => (
                <div 
                  key={sensor.id} 
                  className={`bg-white/10 backdrop-blur-md rounded-xl p-4 flex justify-between items-center ${
                    !sensor.is_active ? 'opacity-50' : ''
                  }`}
                >
                  <div>
                    <h3 className="font-semibold text-white">
                      {sensor.name} 
                      {!sensor.is_active && <span className="text-red-400 text-xs ml-2">(Inactif)</span>}
                    </h3>
                    <p className="text-white/70 text-sm">
                      {sensor.room?.name} - {sensor.room?.building?.name}
                    </p>
                    <p className="text-white/50 text-xs">
                      Type: {sensor.type} | Topic: {sensor.mqtt_topic} | Position: {sensor.position}
                      {sensor.battery_level && ` | Batterie: ${sensor.battery_level}%`}
                    </p>
                  </div>
                  <div className="flex space-x-2">
                    <button
                      onClick={() => {
                        setEditingId(sensor.id);
                        setSensorForm({
                          name: sensor.name,
                          room_id: sensor.room_id,
                          type: sensor.type,
                          mqtt_topic: sensor.mqtt_topic,
                          position: sensor.position,
                          mac_address: sensor.mac_address || ''
                        });
                        setShowSensorForm(true);
                      }}
                      className="text-blue-400 hover:text-blue-300"
                    >
                      <Edit2 className="w-5 h-5" />
                    </button>
                    <button
                      onClick={() => deleteSensor(sensor.id)}
                      className="text-red-400 hover:text-red-300"
                      title="Désactiver"
                    >
                      <Trash2 className="w-5 h-5" />
                    </button>
                  </div>
                </div>
              ))}
              
              {sensors.filter(s => s.is_active).length === 0 && (
                <div className="text-center py-8 text-white/50">
                  {rooms.length === 0
                    ? 'Ajoutez d\'abord une salle'
                    : 'Aucun capteur actif. Configurez votre RuuviTag.'}
                </div>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default Admin;