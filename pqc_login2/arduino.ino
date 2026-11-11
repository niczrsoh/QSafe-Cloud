#include <Arduino.h>
#include <PQCMicro.h>
#include <mbedtls/base64.h>
#include <mbedtls/md.h>

// PQCMicro currently provides ML-KEM-512. Keep server-side algorithm at ML-KEM-512 too.
static const size_t MLKEM_PUBLIC_KEY_RAW_BYTES = MLKEM512::PUBLICKEYBYTES;    // 800
static const size_t MLKEM_CIPHERTEXT_BYTES = MLKEM512::CIPHERTEXTBYTES;       // 768
static const size_t MLKEM_SHARED_SECRET_BYTES = MLKEM512::SHAREDKEYBYTES;     // 32
static const char *MLKEM_FLASH_LABEL = "pqc_login_mlkem";

uint8_t hardware_root_key[32];
String hardware_id_hex = "";
PQCKyber kyber;
bool mlkem_ready = false;

void printHexByte(uint8_t b) {
    if (b < 16) Serial.print('0');
    Serial.print(b, HEX);
}

void appendHexByte(String &out, uint8_t b) {
    char buf[3];
    snprintf(buf, sizeof(buf), "%02x", b);
    out += buf;
}

String bytesToHex(const uint8_t *bytes, size_t len) {
    String out = "";
    for (size_t i = 0; i < len; i++) appendHexByte(out, bytes[i]);
    return out;
}

void sha256Start(mbedtls_md_context_t &ctx) {
    mbedtls_md_init(&ctx);
    mbedtls_md_setup(&ctx, mbedtls_md_info_from_type(MBEDTLS_MD_SHA256), 0);
    mbedtls_md_starts(&ctx);
}

String base64Encode(const uint8_t *input, size_t inputLen) {
    size_t outLen = 0;
    mbedtls_base64_encode(NULL, 0, &outLen, input, inputLen);
    uint8_t *out = (uint8_t *)malloc(outLen + 1);
    if (!out) return "";
    if (mbedtls_base64_encode(out, outLen + 1, &outLen, input, inputLen) != 0) {
        free(out);
        return "";
    }
    out[outLen] = 0;
    String encoded = String((char *)out);
    free(out);
    return encoded;
}

bool base64DecodeToBuffer(const String &input, uint8_t *out, size_t outCapacity, size_t *outLen) {
    return mbedtls_base64_decode(out, outCapacity, outLen, (const uint8_t *)input.c_str(), input.length()) == 0;
}

void generateLegacyHardwareKey() {
    uint64_t chipId = ESP.getEfuseMac();
    uint8_t seed[8];

    for (int i = 0; i < 8; i++) {
        seed[i] = (uint8_t)(chipId >> (i * 8));
    }

    mbedtls_md_context_t ctx;
    sha256Start(ctx);
    mbedtls_md_update(&ctx, seed, 8);
    mbedtls_md_finish(&ctx, hardware_root_key);
    mbedtls_md_free(&ctx);

    hardware_id_hex = bytesToHex(hardware_root_key, 32);
}

String computeDeviceIdFromMlkemPublicKey() {
    uint8_t digest[32];
    mbedtls_md_context_t ctx;
    sha256Start(ctx);
    mbedtls_md_update(&ctx, kyber.getPublicKey(), MLKEM_PUBLIC_KEY_RAW_BYTES);
    mbedtls_md_finish(&ctx, digest);
    mbedtls_md_free(&ctx);
    return bytesToHex(digest, 32);
}

void loadOrCreateMlkemKeypair() {
#if defined(ESP32) || defined(ESP_PLATFORM)
    mlkem_ready = kyber.loadKeysFromFlash(MLKEM_FLASH_LABEL);
#endif

    if (!mlkem_ready) {
        Serial.println("MLKEM_KEYGEN_START");
        mlkem_ready = kyber.generateKeys();
#if defined(ESP32) || defined(ESP_PLATFORM)
        if (mlkem_ready) {
            kyber.saveKeysToFlash(MLKEM_FLASH_LABEL);
        }
#endif
    }

    if (mlkem_ready && kyber.hasPublicKey() && kyber.hasSecretKey()) {
        hardware_id_hex = computeDeviceIdFromMlkemPublicKey();
    }
}

void processRegisterMlkem() {
    if (!mlkem_ready || !kyber.hasPublicKey()) {
        Serial.println("MLKEM_ERROR:ML-KEM keypair not available");
        Serial.flush();
        return;
    }

    Serial.print("DEVICE_ID:");
    Serial.println(hardware_id_hex);
    Serial.println("MLKEM_ALG:ML-KEM-512");
    Serial.print("MLKEM_PK_B64:");
    Serial.println(base64Encode(kyber.getPublicKey(), MLKEM_PUBLIC_KEY_RAW_BYTES));
    Serial.flush();
}

void processMlkemLogin(String payload) {
    int first = payload.indexOf(':');
    int second = payload.indexOf(':', first + 1);
    if (first <= 0 || second <= first) {
        Serial.println("MLKEM_ERROR:Bad LOGIN_DECAP format");
        return;
    }

    String ciphertextB64 = payload.substring(0, first);
    String challenge = payload.substring(first + 1, second);
    String username = payload.substring(second + 1);

    uint8_t ciphertext[MLKEM_CIPHERTEXT_BYTES];
    size_t ciphertextLen = 0;
    if (!base64DecodeToBuffer(ciphertextB64, ciphertext, sizeof(ciphertext), &ciphertextLen)
        || ciphertextLen != MLKEM_CIPHERTEXT_BYTES) {
        Serial.println("MLKEM_ERROR:Invalid ciphertext");
        return;
    }

    if (!mlkem_ready || !kyber.decapsulate(ciphertext) || !kyber.hasSharedSecret()) {
        Serial.println("MLKEM_ERROR:Decapsulation failed");
        return;
    }

    uint8_t proof[32];
    mbedtls_md_context_t ctx;
    sha256Start(ctx);
    mbedtls_md_update(&ctx, kyber.getSharedSecret(), MLKEM_SHARED_SECRET_BYTES);
    mbedtls_md_update(&ctx, (const uint8_t *)challenge.c_str(), challenge.length());
    mbedtls_md_update(&ctx, (const uint8_t *)username.c_str(), username.length());
    mbedtls_md_update(&ctx, (const uint8_t *)hardware_id_hex.c_str(), hardware_id_hex.length());
    mbedtls_md_finish(&ctx, proof);
    mbedtls_md_free(&ctx);

    Serial.print("DEVICE_ID:");
    Serial.println(hardware_id_hex);
    Serial.print("MLKEM_PROOF:");
    Serial.println(bytesToHex(proof, sizeof(proof)));
    Serial.flush();
}

void processLegacyChallenge(String challenge) {
    uint8_t signature[32];

    mbedtls_md_context_t ctx;
    sha256Start(ctx);
    mbedtls_md_update(&ctx, hardware_root_key, 32);
    mbedtls_md_update(&ctx, (const uint8_t*)challenge.c_str(), challenge.length());
    mbedtls_md_finish(&ctx, signature);
    mbedtls_md_free(&ctx);

    Serial.print("PQC_KEY:");
    Serial.println(hardware_id_hex);
    Serial.flush();
    delay(80);

    Serial.print("PQC_SIG:");
    for (int i = 0; i < 32; i++) printHexByte(signature[i]);
    Serial.println();
    Serial.flush();
}

void setup() {
    Serial.begin(115200);
    delay(1000);
    generateLegacyHardwareKey();
    loadOrCreateMlkemKeypair();
    Serial.println(mlkem_ready ? "PQCMICRO_MLKEM512_READY" : "PQCMICRO_MLKEM512_ERROR");
}

void loop() {
    if (Serial.available()) {
        String input = Serial.readStringUntil('\n');
        input.trim();

        if (input == "REGISTER_MLKEM") {
            processRegisterMlkem();
        } else if (input.startsWith("LOGIN_DECAP:")) {
            processMlkemLogin(input.substring(12));
        } else if (input == "BIND") {
            Serial.print("PQC_ID:");
            Serial.println(hardware_id_hex);
            Serial.flush();
        } else if (input.startsWith("CHALLENGE:")) {
            processLegacyChallenge(input.substring(10));
        } else if (input == "PING") {
            Serial.println("PONG");
        }
    }
    delay(10);
}
