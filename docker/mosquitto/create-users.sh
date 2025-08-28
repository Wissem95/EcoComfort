#!/bin/bash

# Create MQTT users for EcoComfort system
set -e

PASSWD_FILE="/mosquitto/config/passwd"
MQTT_USERNAME=${MQTT_USERNAME:-ecocomfort}
MQTT_PASSWORD=${MQTT_PASSWORD:-mqtt_secret}

echo "🔐 Creating MQTT user authentication..."

# Create password file with the main user
mosquitto_passwd -c "$PASSWD_FILE" "$MQTT_USERNAME"

echo "✅ MQTT authentication configured for user: $MQTT_USERNAME"
echo "📝 Password file created at: $PASSWD_FILE"

# Set proper permissions
chmod 600 "$PASSWD_FILE"

echo "🔒 Authentication file permissions set"