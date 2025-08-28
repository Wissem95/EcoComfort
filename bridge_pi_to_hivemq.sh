#!/bin/bash

echo "🌉 BRIDGE MQTT: Pi (Local) → HiveMQ (Cloud)"
echo "=============================================="
echo "📥 Source: Pi MQTT (192.168.1.216:1883)"
echo "📤 Destination: HiveMQ Cloud (d3d4e2f99dec42d1b9a73709c8fa4af0.s1.eu.hivemq.cloud:8883)"
echo ""

# Fonction pour republier un message
republish_message() {
    local topic="$1"
    local message="$2"
    
    # Determiner le topic de destination HiveMQ
    if [[ "$topic" == *"202481587021839"* ]]; then
        dest_topic="112"  # Température
        data_type="température"
    elif [[ "$topic" == *"202481587113159"* ]]; then
        dest_topic="114"  # Humidité
        data_type="humidité"
    elif [[ "$topic" == *"202481591702492"* ]] || [[ "$topic" == *"202481591484002"* ]] || [[ "$topic" == *"202481598160802"* ]] || [[ "$topic" == *"202481601481463"* ]]; then
        dest_topic="127"  # Mouvement/Accéléromètre
        data_type="mouvement"
    else
        dest_topic="ecocomfort/unknown"
        data_type="unknown"
    fi
    
    # Publier vers HiveMQ Cloud
    mosquitto_pub -h d3d4e2f99dec42d1b9a73709c8fa4af0.s1.eu.hivemq.cloud \
        -p 8883 -u ecoconfort -P Ecoconfort123 \
        -t "$dest_topic" -m "$message" --insecure
    
    timestamp=$(date '+%H:%M:%S')
    echo "[$timestamp] 🌉 Bridged: $data_type → HiveMQ topic $dest_topic"
}

# Écouter le Pi MQTT et republier vers HiveMQ
echo "🔄 Démarrage du bridge..."
echo ""

mosquitto_sub -h 192.168.1.216 -p 1883 -t "gw-event/status/+" | \
while IFS= read -r line; do
    if [[ -n "$line" ]]; then
        # Extraire le topic du message si possible
        topic="gw-event/status/ruuvitag"
        
        # Republier le message
        republish_message "$topic" "$line"
    fi
done