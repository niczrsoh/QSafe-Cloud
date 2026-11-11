"""
PQC Secure Channel — Capstone Project
======================================
Demonstrates a hybrid post-quantum secured channel combining:
  - ML-DSA-65   : CA-issued certificate authenticating the server's identity
  - X25519      : classical Diffie-Hellman half of the hybrid key exchange
  - ML-KEM-768  : post-quantum half of the hybrid key exchange
  - AES-256-GCM : symmetric encryption using the derived session key

Run: python pqc_secure_channel.py
"""

import json
import time
import hashlib
import os

from pqcrypto.sign.ml_dsa_65 import keygen as dsa_keygen, sign, verify
from pqcrypto.kem.ml_kem_768 import keygen as kem_keygen, encaps, decaps
from cryptography.hazmat.primitives.asymmetric.x25519 import X25519PrivateKey
from cryptography.hazmat.primitives.ciphers.aead import AESGCM

SEP = "=" * 60


def log(title):
    print(f"\n{SEP}\n{title}\n{SEP}")


def cert_bytes(cert_data: dict) -> bytes:
    """Canonical bytes of certificate data — must match exactly what was signed."""
    return json.dumps(cert_data, sort_keys=True).encode()


# ----------------------------------------------------------------------
# STAGE 0 — Certificate Authority setup (happens once, out of band)
# ----------------------------------------------------------------------
log("STAGE 0: Certificate Authority Setup")

ca_public_key, ca_secret_key = dsa_keygen()
print(f"CA root keypair generated (ML-DSA-65). CA public key: {len(ca_public_key)} bytes")
print("-> In real life, this public key ships baked into every client (like a browser's trust store).")


# ----------------------------------------------------------------------
# STAGE 1 — Server generates its long-term identity key and gets certified
# ----------------------------------------------------------------------
log("STAGE 1: Server Identity + Certificate Issuance")

server_identity_public, server_identity_secret = dsa_keygen()

cert_data = {
    "subject": "secure-service.example",
    "issuer": "PQC-Lab-Root-CA",
    "identity_public_key": server_identity_public.hex(),
    "algorithm": "ML-DSA-65",
    "issued_at": int(time.time()),
    "expires_at": int(time.time()) + 365 * 24 * 3600,
}
ca_signature = sign(ca_secret_key, cert_bytes(cert_data))
server_certificate = {"data": cert_data, "signature": ca_signature.hex()}

print(f"Server certificate issued for '{cert_data['subject']}'.")
print(f"Certificate signature size: {len(ca_signature)} bytes")
total_cert_size = len(json.dumps(server_certificate).encode())
print(f"Total certificate size (JSON): {total_cert_size} bytes")


# ----------------------------------------------------------------------
# STAGE 2 — Client connects: verifies the certificate before trusting anything
# ----------------------------------------------------------------------
log("STAGE 2: Client Verifies Server Certificate")

received_cert = server_certificate  # simulates receiving this over the network
cert_ok = False
try:
    verify(
        ca_public_key,
        cert_bytes(received_cert["data"]),
        bytes.fromhex(received_cert["signature"]),
    )
    cert_ok = True
    print("Certificate signature VALID — CA really did issue this for this server.")
except Exception:
    print("Certificate signature INVALID — aborting connection.")

if not cert_ok:
    raise SystemExit("Client aborts: cannot trust an unverified server.")

server_identity_public_verified = bytes.fromhex(received_cert["data"]["identity_public_key"])


# ----------------------------------------------------------------------
# STAGE 3 — Hybrid key exchange (X25519 + ML-KEM), bound to the server's identity
# ----------------------------------------------------------------------
log("STAGE 3: Hybrid Key Exchange (X25519 + ML-KEM-768)")

# Server generates EPHEMERAL keys for this session (forward secrecy — Day 2's lesson)
server_x25519_priv = X25519PrivateKey.generate()
server_x25519_pub = server_x25519_priv.public_key()
server_mlkem_pub, server_mlkem_secret = kem_keygen()

# Server signs its ephemeral public keys with its LONG-TERM identity key.
# This is what stops a man-in-the-middle from swapping in their own ephemeral keys.
ephemeral_bundle = {
    "x25519_pub": server_x25519_pub.public_bytes_raw().hex(),
    "mlkem_pub": server_mlkem_pub.hex(),
}
ephemeral_signature = sign(server_identity_secret, cert_bytes(ephemeral_bundle))
print("Server generated ephemeral X25519 + ML-KEM keys and signed them with its identity key.")

# --- Client side ---
# 1. Verify the ephemeral keys were really signed by the certified identity key
try:
    verify(
        server_identity_public_verified,
        cert_bytes(ephemeral_bundle),
        ephemeral_signature,
    )
    print("Ephemeral key bundle signature VALID — bound to the certified server identity.")
except Exception:
    raise SystemExit("Client aborts: ephemeral keys not authentically signed. Possible MITM.")

# 2. Client does its half of X25519
client_x25519_priv = X25519PrivateKey.generate()
client_x25519_pub = client_x25519_priv.public_key()
client_x25519_secret = client_x25519_priv.exchange(server_x25519_pub)

# 3. Client encapsulates against server's ML-KEM public key
mlkem_ciphertext, client_mlkem_secret = encaps(server_mlkem_pub)

# 4. Client derives the combined session key
client_combined = client_x25519_secret + client_mlkem_secret
client_session_key = hashlib.sha256(client_combined).digest()
print(f"Client derived session key: {client_session_key.hex()[:32]}...")

# --- Server side: recompute the same key ---
server_x25519_secret = server_x25519_priv.exchange(client_x25519_pub)
server_mlkem_secret_recovered = decaps(server_mlkem_secret, mlkem_ciphertext)
server_combined = server_x25519_secret + server_mlkem_secret_recovered
server_session_key = hashlib.sha256(server_combined).digest()
print(f"Server derived session key: {server_session_key.hex()[:32]}...")

assert client_session_key == server_session_key, "Session keys did not match!"
print("Both sides independently derived the SAME session key.")


# ----------------------------------------------------------------------
# STAGE 4 — Use the session key for real symmetric encryption (AES-256-GCM)
# ----------------------------------------------------------------------
log("STAGE 4: Encrypted Message Exchange (AES-256-GCM)")

aesgcm_client = AESGCM(client_session_key)
nonce = os.urandom(12)
plaintext = b"User A send 100 ETH to User B"
ciphertext = aesgcm_client.encrypt(nonce, plaintext, None)
print(f"Client encrypts: {plaintext}")
print(f"Ciphertext (hex): {ciphertext.hex()}")

aesgcm_server = AESGCM(server_session_key)
decrypted = aesgcm_server.decrypt(nonce, ciphertext, None)
print(f"Server decrypts: {decrypted}")
assert decrypted == plaintext
print("Message integrity + confidentiality confirmed.")


# ----------------------------------------------------------------------
# STAGE 5 — Summary report
# ----------------------------------------------------------------------
log("STAGE 5: Handshake Cost Summary")

handshake_bytes = (
    total_cert_size
    + len(server_x25519_pub.public_bytes_raw())
    + len(server_mlkem_pub)
    + len(ephemeral_signature)
    + len(client_x25519_pub.public_bytes_raw())
    + len(mlkem_ciphertext)
)
print(f"Certificate:              {total_cert_size:>6} bytes")
print(f"Server ephemeral pubkeys: {len(server_x25519_pub.public_bytes_raw()) + len(server_mlkem_pub):>6} bytes")
print(f"Ephemeral signature:      {len(ephemeral_signature):>6} bytes")
print(f"Client X25519 pubkey:     {len(client_x25519_pub.public_bytes_raw()):>6} bytes")
print(f"ML-KEM ciphertext:        {len(mlkem_ciphertext):>6} bytes")
print(f"{'-'*30}")
print(f"TOTAL handshake overhead:  {handshake_bytes:>6} bytes")
print(f"\n(Compare: an equivalent classical-only ECDSA+X25519 handshake is roughly 500-800 bytes total.)")