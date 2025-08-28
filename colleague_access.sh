#!/bin/bash

echo "=================================================="
echo "🏠 EcoComfort IoT - Accès Données RuuviTag Temps Réel"
echo "=================================================="
echo ""
echo "📊 Données en cours de réception depuis HiveMQ Cloud..."
echo ""
echo "🌡️ TEMPÉRATURE (Topic 112):"
echo "------------------------------------------"

# Lancer en arrière-plan les 3 subscribers
{
    mosquitto_sub -h d3d4e2f99dec42d1b9a73709c8fa4af0.s1.eu.hivemq.cloud \
        -p 8883 -u ecoconfort -P Ecoconfort123 \
        -t "112" --insecure | \
    while read line; do
        timestamp=$(date '+%H:%M:%S')
        echo "[$timestamp] 🌡️ TEMPÉRATURE: $line"
    done
} &

{
    mosquitto_sub -h d3d4e2f99dec42d1b9a73709c8fa4af0.s1.eu.hivemq.cloud \
        -p 8883 -u ecoconfort -P Ecoconfort123 \
        -t "114" --insecure | \
    while read line; do
        timestamp=$(date '+%H:%M:%S')
        echo "[$timestamp] 💧 HUMIDITÉ: $line"
    done
} &

{
    mosquitto_sub -h d3d4e2f99dec42d1b9a73709c8fa4af0.s1.eu.hivemq.cloud \
        -p 8883 -u ecoconfort -P Ecoconfort123 \
        -t "127" --insecure | \
    while read line; do
        timestamp=$(date '+%H:%M:%S')
        echo "[$timestamp] 📱 MOUVEMENT: $line"
    done
} &

{
    mosquitto_sub -h d3d4e2f99dec42d1b9a73709c8fa4af0.s1.eu.hivemq.cloud \
        -p 8883 -u ecoconfort -P Ecoconfort123 \
        -t "ecocomfort/#" --insecure | \
    while read line; do
        timestamp=$(date '+%H:%M:%S')
        echo "[$timestamp] 📡 DONNÉES: $line"
    done
} &

echo ""
echo "🔄 Écoute active sur HiveMQ Cloud..."
echo "📍 Cluster: d3d4e2f99dec42d1b9a73709c8fa4af0.s1.eu.hivemq.cloud:8883"
echo "🏷️ Topics: 112 (température), 114 (humidité), 127 (mouvement)"
echo ""
echo "⚠️ Appuyez sur Ctrl+C pour arrêter"
echo ""

# Attendre tous les processus en arrière-plan
wait