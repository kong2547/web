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
const unsigned long scheduleFetchInterval = 5000;
unsigned long lastScheduleCheck[10] = {0};
const unsigned long scheduleInterval = 5000;

// ----------------- STATIC IP CONFIG -----------------
bool useStaticIP = false;
String staticIP = "172.26.30.12";     // ตั้งค่า IP คงที่
String gatewayIP = "172.26.30.254";
String subnetIP  = "255.255.255.0";
String dnsIP     = "8.8.8.8";

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

// ----------------- RELAY CONTROL (แก้ไขแล้ว) -----------------
void saveRelayState(int i, bool state) {
  prefs.begin("relay", false);
  prefs.putBool(("relay_" + String(relayPins[i])).c_str(), state);
  prefs.end();
}

// ✅ รีเลย์ Active HIGH / LED Active HIGH (ทำงานตรงกัน)
void applyRelayState(int i, bool state, const char* action) {
  if (relayState[i] == state) return;
  relayState[i] = state;

  digitalWrite(relayPins[i], relayState[i] ? HIGH : LOW);
  digitalWrite(ledPins[i], relayState[i] ? HIGH : LOW);

  saveRelayState(i, state);

  if (String(action) == "manual_sync" || String(action) == "button_press") {
    manualOverride[i] = true;
    lastManual[i] = millis();
  }

  Serial.printf("[%s] GPIO %d → %s (%s)\n",
                getTimeString().c_str(),
                relayPins[i],
                state ? "ON" : "OFF",
                action);

  if (wifiConnected) {
    WiFiClient client;
    HTTPClient http;
    String url = serverURL + "?cmd=update&esp_name=" + espName +
                 "&gpio=" + relayPins[i] +
                 "&status=" + (state ? "1" : "0") +
                 "&action=" + action;
    if (http.begin(client, url)) {
      http.GET(); http.end();
    }
  }
}

// ตรวจปุ่มกดจริง
void checkButton(int i) {
  static bool last[10] = {HIGH};
  bool st = digitalRead(buttonPins[i]);
  if (last[i] == HIGH && st == LOW) {
    applyRelayState(i, !relayState[i], "button_press");
  }
  last[i] = st;
}

// ----------------- MANUAL SYNC -----------------
void fetchManualCommand(int i) {
  if (!wifiConnected) return;
  WiFiClient client;
  HTTPClient http;
  String url = serverURL + "?cmd=get_status&esp_name=" + espName + "&gpio=" + relayPins[i];
  if (http.begin(client, url)) {
    int code = http.GET();
    if (code == 200) {
      String payload = http.getString();
      StaticJsonDocument<200> doc;
      if (deserializeJson(doc, payload) == DeserializationError::Ok) {
        bool desired = doc["status"];
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
        int hh = t.substring(0,2).toInt();
        int mm = t.substring(3,5).toInt();
        int ss = t.substring(6,8).toInt();
        int day = doc["day"] | 1;
        int month = doc["month"] | 1;
        int year = doc["year"] | 2025;
        setTime(hh, mm, ss, day, month, year);
      }
    }
    http.end();
  }
}

// ----------------- SCHEDULE -----------------
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
    }
    http.end();
  }
}

// ✅ ตรวจวันที่และเวลา
void applyLocalSchedule(int i) {
  if (manualOverride[i] && millis() - lastManual[i] < 5*60*1000) return;
  StaticJsonDocument<4096> doc;
  if (deserializeJson(doc, localScheduleJson)) return;

  String nowStr = getTimeString();
  String currentDate = getDateString();
  String dayStr[] = {"", "Sun","Mon","Tue","Wed","Thu","Fri","Sat"};
  String currentDay = dayStr[weekday()];

  for (JsonObject s : doc.as<JsonArray>()) {
    if (!s["enabled"]) continue;
    if (String((const char*)s["esp_name"]) != espName) continue;

    int scheduleGPIO = s["gpio_pin"];
    if (scheduleGPIO != relayPins[i]) continue;

    String wd = s["weekdays"];
    if (wd.indexOf(currentDay) == -1) continue;

    String startDate = s["start_date"];
    String endDate   = s["end_date"];
    if (currentDate < startDate || currentDate > endDate) continue;

    String st = s["start_time"];
    String et = s["end_time"];
    bool isOn = (String((const char*)s["mode"]) == "on");

    if (nowStr >= st && nowStr < et) {
      applyRelayState(i, isOn, "schedule");
    } else {
      applyRelayState(i, !isOn, "schedule_end");
    }
  }
}

// ----------------- REPORT IP -----------------
void reportNetworkConfig() {
  if (!wifiConnected) return;
  WiFiClient client;
  HTTPClient http;
  String url = serverURL + "?cmd=update_ip"
              + "&esp_name=" + espName
              + "&ip=" + WiFi.localIP().toString()
              + "&gateway=" + WiFi.gatewayIP().toString()
              + "&subnet=" + WiFi.subnetMask().toString()
              + "&dns=" + WiFi.dnsIP().toString()
              + "&mode=" + String(useStaticIP ? "static" : "dhcp");
  if (http.begin(client, url)) {
    http.GET(); http.end();
  }
}

// ----------------- SETUP -----------------
void setup() {
  Serial.begin(115200);
  pinMode(BOOT_PIN, INPUT_PULLUP);
  pinMode(WIFI_LED, OUTPUT);
  pinMode(MODE_LED, OUTPUT);
  loadParams();

  WiFiManager wm;
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

  wm.addParameter(&p1); wm.addParameter(&p2);
  wm.addParameter(&p3); wm.addParameter(&p4); wm.addParameter(&p5);
  wm.addParameter(&p6); wm.addParameter(&p7);
  wm.addParameter(&p8); wm.addParameter(&p9); wm.addParameter(&p10);

  if (!wm.autoConnect("ESP32_ConfigAP")) ESP.restart();

  espName = p1.getValue(); serverURL = p2.getValue();
  relayPinsStr = p3.getValue(); buttonPinsStr = p4.getValue(); ledPinsStr = p5.getValue();
  useStaticIP = String(p6.getValue()) == "1";
  staticIP = p7.getValue(); gatewayIP = p8.getValue(); subnetIP = p9.getValue(); dnsIP = p10.getValue();
  saveParams();

  if (useStaticIP) {
    IPAddress ip, gw, sn, dns;
    if (ip.fromString(staticIP) && gw.fromString(gatewayIP) && sn.fromString(subnetIP) && dns.fromString(dnsIP)) {
      WiFi.config(ip, gw, sn, dns);
      Serial.printf("📡 Static IP: %s\n", staticIP.c_str());
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
    prefs.end();

    digitalWrite(relayPins[i], LOW);
    digitalWrite(ledPins[i], LOW);
  }

  wifiConnected = WiFi.isConnected();
  if (wifiConnected) {
    digitalWrite(WIFI_LED, HIGH);
    syncTimeFromServer();
    fetchFullSchedule();
    reportNetworkConfig();
  }

  Serial.println("===== ESP32 SMART CONTROLLER READY =====");
  Serial.println("ESP Name: " + espName);
  Serial.println("Server: " + serverURL);
}

// ----------------- LOOP -----------------
void loop() {
  checkBootReset();
  wifiConnected = (WiFi.status() == WL_CONNECTED);
  if (wifiConnected) digitalWrite(WIFI_LED, HIGH);
  else blinkWifiTrying();

  if (wifiConnected && millis() % 60000 < 500) syncTimeFromServer();
  if (wifiConnected && millis() - lastScheduleFetch > scheduleFetchInterval) {
    fetchFullSchedule();
    lastScheduleFetch = millis();
  }

  for (int i = 0; i < relayCount; i++) {
    checkButton(i);
    fetchManualCommand(i);
    if (millis() - lastScheduleCheck[i] > scheduleInterval) {
      applyLocalSchedule(i);
      lastScheduleCheck[i] = millis();
    }
  }

  delay(200);
}
