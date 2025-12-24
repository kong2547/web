/*
 * ============================================================
 *  ESP32 SMART CONTROLLER (Stable + Power Resume + Static IP)
 *  Board: ESP32 Relay X4 v1.1 (No external RTC)
 *  Author: Suphawit , thanaphat
 * ============================================================
 *  ✅ จำสถานะรีเลย์หลังไฟดับ (Power Resume)
 *  ✅ คงสถานะ Manual หลังรีเซต (Manual Memory Protect)
 *  ✅ WiFiManager + Static IP + Boot Sync
 *  ✅ ดึงเวลาและตารางจาก Server อัตโนมัติ
 *  ✅ มี Offline Cache ของตารางใน Flash
 *  ✅ Override แบบหน่วงเวลา (Manual / Schedule ทำงานร่วมกัน)
 *  ✅ Schedule เสถียร ไม่กระพริบ
 *  ✅ ส่งข้อมูล Network (IP/Gateway/Subnet/DNS/Mode)
 *  ✅ รายงานชื่อ ESP (espName) อัตโนมัติหลังเชื่อมต่อ Wi-Fi
 * ============================================================
 */
#include <WiFi.h>
#include <WiFiManager.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>
#include <TimeLib.h>

// ----------------- PIN CONFIG -----------------
#define BOOT_PIN 0
#define WIFI_LED 18
#define MODE_LED 19

// ----------------- GLOBAL -----------------
Preferences prefs;

String espName   = "ESP32_002";
String serverURL = "http://172.26.30.10/webcontrol/web/api.php";

String relayPinsStr  = "25,26";
String buttonPinsStr = "13,14";
String ledPinsStr    = "16,17";

int relayPins[10], buttonPins[10], ledPins[10];
bool relayState[10] = {0};
bool manualOverride[10] = {false};
unsigned long lastManual[10] = {0};
int relayCount = 0;

bool wifiConnected = false;
bool scheduleActive = false;

String localScheduleJson = "[]";
unsigned long lastScheduleFetch = 0;
unsigned long lastTimeSync = 0;

const unsigned long scheduleFetchInterval = 60000;
const unsigned long scheduleInterval = 3000; // 3 วินาที
unsigned long lastScheduleCheck[10] = {0};

// ----------------- STATIC IP CONFIG -----------------
bool useStaticIP = false;
String staticIP   = "172.26.30.12";
String gatewayIP  = "172.26.30.254";
String subnetIP   = "255.255.255.0";
String dnsIP      = "203.158.177.9"; // DNS ของมหาวิทยาลัย

// ----------------- POWER RESUME CONTROL -----------------
unsigned long bootTime = 0;
const unsigned long syncDelayAfterBoot = 10000;

// ----------------- UTILS -----------------
int parsePins(String str, int *arr) {
  int count = 0;
  char buf[64];
  str.toCharArray(buf, sizeof(buf));
  char *tok = strtok(buf, ",");
  while (tok) { arr[count++] = atoi(tok); tok = strtok(NULL, ","); }
  return count;
}

String getTimeString() {
  char buf[16];
  sprintf(buf, "%02d:%02d:%02d", hour(), minute(), second());
  return String(buf);
}

String getDateString() {
  char buf[11];
  sprintf(buf, "%04d-%02d-%02d", year(), month(), day());
  return String(buf);
}

void blinkWifiTrying() {
  static unsigned long t = 0;
  if (millis() - t > 500) {
    t = millis();
    digitalWrite(WIFI_LED, !digitalRead(WIFI_LED));
  }
}

// ----------------- LOAD/SAVE PARAMS -----------------
void loadParams() {
  prefs.begin("esp32", false);
  espName = prefs.getString("espName", espName);
  serverURL = prefs.getString("serverURL", serverURL);
  relayPinsStr = prefs.getString("relayPins", relayPinsStr);
  buttonPinsStr = prefs.getString("buttonPins", buttonPinsStr);
  ledPinsStr = prefs.getString("ledPins", ledPinsStr);
  useStaticIP = prefs.getBool("useStaticIP", false);
  staticIP = prefs.getString("staticIP", staticIP);
  gatewayIP = prefs.getString("gatewayIP", gatewayIP);
  subnetIP = prefs.getString("subnetIP", subnetIP);
  dnsIP = prefs.getString("dnsIP", dnsIP);
  prefs.end();
}

void saveParams() {
  prefs.begin("esp32", false);
  prefs.putString("espName", espName);
  prefs.putString("serverURL", serverURL);
  prefs.putString("relayPins", relayPinsStr);
  prefs.putString("buttonPins", buttonPinsStr);
  prefs.putString("ledPins", ledPinsStr);
  prefs.putBool("useStaticIP", useStaticIP);
  prefs.putString("staticIP", staticIP);
  prefs.putString("gatewayIP", gatewayIP);
  prefs.putString("subnetIP", subnetIP);
  prefs.putString("dnsIP", dnsIP);
  prefs.end();
}

// ----------------- RESET WIFI -----------------
void checkBootReset() {
  static unsigned long pressStart = 0;
  if (digitalRead(BOOT_PIN) == LOW) {
    if (pressStart == 0) pressStart = millis();
    if (millis() - pressStart > 5000) {
      Serial.println("🧹 Reset WiFi Config...");
      WiFiManager wm; wm.resetSettings();
      prefs.clear();
      delay(300);
      ESP.restart();
    }
  } else pressStart = 0;
}

// ----------------- SEND NETWORK INFO TO SERVER -----------------
void sendNetworkInfoToServer() {
  if (!wifiConnected) return;
  WiFiClient client;
  HTTPClient http;

  String mode = useStaticIP ? "static" : "dhcp";
  String url = serverURL + "?cmd=update_ip" +
               "&esp_name=" + espName +
               "&ip=" + WiFi.localIP().toString() +
               "&gateway=" + WiFi.gatewayIP().toString() +
               "&subnet=" + WiFi.subnetMask().toString() +
               "&dns=" + WiFi.dnsIP().toString() +
               "&mode=" + mode;

  Serial.println("🌐 Sending network info to server...");
  Serial.println(url);

  if (http.begin(client, url)) {
    int code = http.GET();
    Serial.printf("Server response: %d\n", code);
    if (code != 200) Serial.println(http.getString());
    http.end();
  }
}

// ----------------- REGISTER ESP NAME TO SERVER -----------------
void registerEspNameToServer() {
  if (!wifiConnected) return;
  WiFiClient client;
  HTTPClient http;

  String url = serverURL + "?cmd=update_ip"
               "&esp_name=" + espName +
               "&ip=" + WiFi.localIP().toString() +
               "&gateway=" + WiFi.gatewayIP().toString() +
               "&subnet=" + WiFi.subnetMask().toString() +
               "&dns=" + WiFi.dnsIP().toString() +
               "&mode=" + (useStaticIP ? "static" : "dhcp");

  if (http.begin(client, url)) {
    int code = http.GET();
    Serial.printf("📡 ESP registered name '%s' to server (%d)\n", espName.c_str(), code);
    http.end();
  }
}

// ----------------- RELAY CONTROL -----------------
void saveRelayState(int i, bool state) {
  prefs.begin("relay", false);
  prefs.putBool(("relay_" + String(relayPins[i])).c_str(), state);
  prefs.end();
}

void applyRelayState(int i, bool state, const char* action) {
  if (relayState[i] == state && String(action) != "boot_sync") return;
  
  relayState[i] = state;
  digitalWrite(relayPins[i], relayState[i] ? HIGH : LOW);
  digitalWrite(ledPins[i], relayState[i] ? HIGH : LOW);
  saveRelayState(i, state);

  if (String(action) == "manual_sync" || String(action) == "button_press") {
    if (state) {
      manualOverride[i] = true;
      lastManual[i] = millis();
      prefs.begin("relay", false);
      prefs.putBool(("manual_" + String(relayPins[i])).c_str(), true);
      prefs.end();
      Serial.printf("🔒 Manual override SET for GPIO %d\n", relayPins[i]);
    } else {
      manualOverride[i] = false;
      prefs.begin("relay", false);
      prefs.putBool(("manual_" + String(relayPins[i])).c_str(), false);
      prefs.end();
      Serial.printf("🔓 Manual override CLEARED for GPIO %d\n", relayPins[i]);
    }
  }

  Serial.printf("[%s] GPIO %d → %s (%s) | ManualOverride: %s\n",
                getTimeString().c_str(),
                relayPins[i],
                state ? "ON" : "OFF",
                action,
                manualOverride[i] ? "YES" : "NO");

  if (wifiConnected) {
    WiFiClient client;
    HTTPClient http;
    String url = serverURL + "?cmd=update&esp_name=" + espName +
                 "&gpio=" + relayPins[i] +
                 "&status=" + (state ? "1" : "0") +
                 "&action=" + action;
    if (http.begin(client, url)) { http.GET(); http.end(); }
  }
}

// ----------------- CHECK BUTTON -----------------
void checkButton(int i) {
  static bool last[10] = {HIGH};
  bool st = digitalRead(buttonPins[i]);
  if (last[i] == HIGH && st == LOW) {
    Serial.printf("🔘 Button pressed for GPIO %d | Current state: %s\n", 
                  relayPins[i], relayState[i] ? "ON" : "OFF");
    applyRelayState(i, !relayState[i], "button_press");
  }
  last[i] = st;
}

// ----------------- MANUAL SYNC -----------------
void fetchManualCommand(int i) {
  if (!wifiConnected) return;

  WiFiClient client;
  HTTPClient http;
  http.setConnectTimeout(3000);
  http.setTimeout(3000);

  String url = serverURL + "?cmd=get_status&esp_name=" + espName + "&gpio=" + relayPins[i];
  if (http.begin(client, url)) {
    int code = http.GET();
    if (code == 200) {
      String payload = http.getString();
      StaticJsonDocument<256> doc;
      if (deserializeJson(doc, payload) == DeserializationError::Ok) {
        bool desired = doc["status"].as<int>() != 0;
        if (desired != relayState[i]) applyRelayState(i, desired, "manual_sync");
      }
    }
    http.end();
  }
}

// ----------------- TIME SYNC -----------------
void syncTimeFromServer() {
  if (!wifiConnected) return;
  WiFiClient client;
  HTTPClient http;
  String url = serverURL + "?cmd=get_time";
  if (http.begin(client, url)) {
    int code = http.GET();
    if (code == 200) {
      String payload = http.getString();
      StaticJsonDocument<200> doc;
      if (deserializeJson(doc, payload) == DeserializationError::Ok) {
        String t = doc["time"];
        setTime(t.substring(0,2).toInt(), t.substring(3,5).toInt(), t.substring(6,8).toInt(),
                doc["day"] | 1, doc["month"] | 1, doc["year"] | 2025);
        Serial.printf("🕒 Time synced: %s\n", getTimeString().c_str());
      }
    }
    http.end();
  }
}

// ----------------- FETCH FULL SCHEDULE -----------------
void fetchFullSchedule() {
  if (!wifiConnected) return;
  WiFiClient client;
  HTTPClient http;
  String url = serverURL + "?cmd=get_schedule&esp_name=" + espName;
  if (http.begin(client, url)) {
    int code = http.GET();
    if (code == 200) {
      localScheduleJson = http.getString();
      prefs.begin("schedule", false);
      prefs.putString("cache", localScheduleJson);
      prefs.end();
      Serial.println("🗂️ Schedule updated from Server");
    }
    http.end();
  }
}

// ----------------- APPLY LOCAL SCHEDULE -----------------
void applyLocalSchedule(int i) {
  if (!wifiConnected) return;

  StaticJsonDocument<4096> doc;
  if (deserializeJson(doc, localScheduleJson) != DeserializationError::Ok) return;

  time_t nowTime = now();
  int currentSeconds = hour(nowTime) * 3600 + minute(nowTime) * 60 + second(nowTime);

  for (JsonObject r : doc.as<JsonArray>()) {
    int gpio = r["gpio_pin"] | -1;
    if (gpio != relayPins[i]) continue;

    String mode = r["mode"] | "on";
    String action = r["action"] | "on";
    String startStr = r["start_time"] | "00:00:00";
    String endStr = r["end_time"] | "00:00:00";
    String weekdays = r["weekdays"] | "";

    int startSec = startStr.substring(0,2).toInt()*3600 + startStr.substring(3,5).toInt()*60 + startStr.substring(6,8).toInt();
    int endSec   = endStr.substring(0,2).toInt()*3600 + endStr.substring(3,5).toInt()*60 + endStr.substring(6,8).toInt();
    String today = String(dayShortStr(weekday(nowTime)));

    // ✅ ใช้ indexOf() แทน contains()
    if (weekdays.length() > 0 && weekdays.indexOf(today) == -1) continue;

    bool active = false;
    if (startSec <= endSec) {
      active = (currentSeconds >= startSec && currentSeconds <= endSec);
    } else {
      active = (currentSeconds >= startSec || currentSeconds <= endSec);
    }

    // ถ้า schedule ทำงานและไม่มี manual override → ทำงานตามตาราง
    if (active && !manualOverride[i]) {
      bool shouldOn = (action == "on" || mode == "on");
      if (relayState[i] != shouldOn) {
        applyRelayState(i, shouldOn, "schedule");
        Serial.printf("📅 Schedule triggered GPIO %d → %s\n", relayPins[i], shouldOn ? "ON" : "OFF");
      }
    }
  }
}

// ----------------- WIFI RECONNECT LOG -----------------
void logWifiReconnect() {
  Serial.printf("🔗 Wi-Fi reconnected: IP=%s\n", WiFi.localIP().toString().c_str());
  if (wifiConnected) {
    WiFiClient client;
    HTTPClient http;
    String url = serverURL +
      "?cmd=log_reconnect"
      "&esp_name=" + espName +
      "&ip=" + WiFi.localIP().toString();
    if (http.begin(client, url)) {
      http.GET();
      http.end();
    }
    sendNetworkInfoToServer();
    registerEspNameToServer();
  }
}

// ----------------- SETUP -----------------
void setup() {
  Serial.begin(115200);
  pinMode(BOOT_PIN, INPUT_PULLUP);
  pinMode(WIFI_LED, OUTPUT);
  pinMode(MODE_LED, OUTPUT);

  loadParams();

  prefs.begin("schedule", true);
  localScheduleJson = prefs.getString("cache", "[]");
  prefs.end();
  Serial.println("🗂️ Loaded cached schedule from Flash");

  WiFiManager wm;
  wm.setConnectRetries(20);
  wm.setConnectTimeout(10);
  wm.setConfigPortalTimeout(0);
  wm.setBreakAfterConfig(true);

  WiFiManagerParameter p1("espName","ESP Name",espName.c_str(),40);
  WiFiManagerParameter p2("serverURL","Server URL",serverURL.c_str(),100);
  WiFiManagerParameter p3("relayPins","Relay Pins",relayPinsStr.c_str(),40);
  WiFiManagerParameter p4("buttonPins","Button Pins",buttonPinsStr.c_str(),40);
  WiFiManagerParameter p5("ledPins","LED Pins",ledPinsStr.c_str(),40);
  WiFiManagerParameter p6("useStaticIP","Use Static (1=Yes,0=No)",useStaticIP?"1":"0",2);
  WiFiManagerParameter p7("staticIP","Static IP",staticIP.c_str(),16);
  WiFiManagerParameter p8("gatewayIP","Gateway IP",gatewayIP.c_str(),16);
  WiFiManagerParameter p9("subnetIP","Subnet",subnetIP.c_str(),16);
  WiFiManagerParameter p10("dnsIP","DNS IP",dnsIP.c_str(),16);

  wm.addParameter(&p1);
  wm.addParameter(&p2);
  wm.addParameter(&p3);
  wm.addParameter(&p4);
  wm.addParameter(&p5);
  wm.addParameter(&p6);
  wm.addParameter(&p7);
  wm.addParameter(&p8);
  wm.addParameter(&p9);
  wm.addParameter(&p10);

  Serial.println("🔁 Trying to reconnect Wi-Fi...");
  if (!wm.autoConnect(espName.c_str())) {
    Serial.printf("⚠️ Wi-Fi not found, starting Config Portal: %s\n", espName.c_str());
    wm.startConfigPortal(espName.c_str());
  }

  espName = p1.getValue();
  serverURL = p2.getValue();
  relayPinsStr = p3.getValue();
  buttonPinsStr = p4.getValue();
  ledPinsStr = p5.getValue();
  useStaticIP = String(p6.getValue()) == "1";
  staticIP = p7.getValue();
  gatewayIP = p8.getValue();
  subnetIP = p9.getValue();
  dnsIP = p10.getValue();

  saveParams();

  if (useStaticIP) {
    IPAddress ip, gw, sn, dns;
    if (ip.fromString(staticIP) && gw.fromString(gatewayIP) &&
        sn.fromString(subnetIP) && dns.fromString(dnsIP)) {
      WiFi.config(ip, gw, sn, dns);
      Serial.printf("📡 Static IP configured: %s\n", staticIP.c_str());
    }
  }

  relayCount = parsePins(relayPinsStr, relayPins);
  parsePins(buttonPinsStr, buttonPins);
  parsePins(ledPinsStr, ledPins);

  for (int i = 0; i < relayCount; i++) {
    pinMode(relayPins[i], OUTPUT);
    pinMode(buttonPins[i], INPUT_PULLUP);
    pinMode(ledPins[i], OUTPUT);

    prefs.begin("relay", true);
    relayState[i] = prefs.getBool(("relay_"+String(relayPins[i])).c_str(), false);
    manualOverride[i] = prefs.getBool(("manual_"+String(relayPins[i])).c_str(), false);
    prefs.end();

    digitalWrite(relayPins[i], relayState[i] ? HIGH : LOW);
    digitalWrite(ledPins[i], relayState[i] ? HIGH : LOW);
  }

  wifiConnected = WiFi.isConnected();
  if (wifiConnected) {
    digitalWrite(WIFI_LED, HIGH);
    sendNetworkInfoToServer();
    registerEspNameToServer();
    syncTimeFromServer();
    fetchFullSchedule();

    delay(3000);
    for (int i = 0; i < relayCount; i++) {
      applyRelayState(i, relayState[i], "boot_sync");
      delay(100);
    }
  }

  bootTime = millis();
  Serial.println("===== ESP32 SMART CONTROLLER READY =====");
  Serial.println("🏷️ Device: " + espName);
  Serial.println("🌐 Server: " + serverURL);
  Serial.printf("🔧 IP Mode: %s\n", useStaticIP ? "Static" : "DHCP");
}

// ----------------- LOOP -----------------
void loop() {
  checkBootReset();
  static bool lastWifiState = false;
  static int wifiRetry = 0;

  wifiConnected = (WiFi.status() == WL_CONNECTED);

  if (wifiConnected) {
    digitalWrite(WIFI_LED, HIGH);
    wifiRetry = 0;
  } else {
    blinkWifiTrying();
    wifiRetry++;
    if (wifiRetry > 10) {
      Serial.println("⚠️ Wi-Fi not found, entering Config Portal...");
      WiFiManager wm;
      wm.startConfigPortal(espName.c_str());
      wifiRetry = 0;
    }
  }

  if (wifiConnected && !lastWifiState) {
    logWifiReconnect();
    sendNetworkInfoToServer();
  }
  lastWifiState = wifiConnected;

  if (wifiConnected && millis() - lastTimeSync > 60000) {
    syncTimeFromServer();
    fetchFullSchedule();
    lastTimeSync = millis();
  }

  for (int i = 0; i < relayCount; i++) {
    checkButton(i);
    if (millis() - bootTime > syncDelayAfterBoot) fetchManualCommand(i);
    if (millis() - lastScheduleCheck[i] > scheduleInterval) {
      applyLocalSchedule(i);
      lastScheduleCheck[i] = millis();
    }
  }

  delay(200);
}
