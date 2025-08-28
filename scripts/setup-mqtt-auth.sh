#!/bin/bash

# Setup MQTT authentication for EcoComfort
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
PASSWD_FILE="$PROJECT_DIR/docker/mosquitto/passwd"

# Default credentials (can be overridden by environment variables)
MQTT_USERNAME=${MQTT_USERNAME:-ecocomfort}
MQTT_PASSWORD=${MQTT_PASSWORD:-mqtt_secret}

echo "🔐 Setting up MQTT authentication..."
echo "Username: $MQTT_USERNAME"

# Check if mosquitto_passwd is available
if ! command -v mosquitto_passwd &> /dev/null; then
    echo "❌ mosquitto_passwd command not found"
    echo "💡 Installing mosquitto client tools..."
    
    # Try to install mosquitto clients
    if command -v apt-get &> /dev/null; then
        sudo apt-get update && sudo apt-get install -y mosquitto-clients
    elif command -v yum &> /dev/null; then
        sudo yum install -y mosquitto
    elif command -v brew &> /dev/null; then
        brew install mosquitto
    else
        echo "❌ Unable to install mosquitto tools automatically"
        echo "Please install mosquitto client tools manually and run this script again"
        exit 1
    fi
fi

# Create password file
echo "📝 Creating password file..."
mosquitto_passwd -c "$PASSWD_FILE" "$MQTT_USERNAME" <<< "$MQTT_PASSWORD"

# Set proper permissions
chmod 600 "$PASSWD_FILE"

echo "✅ MQTT authentication setup complete!"
echo "   Password file: $PASSWD_FILE"
echo "   Username: $MQTT_USERNAME"
echo ""
echo "🔍 Password file contents:"
cat "$PASSWD_FILE"

echo ""
echo "✅ MQTT broker is now configured with authentication"