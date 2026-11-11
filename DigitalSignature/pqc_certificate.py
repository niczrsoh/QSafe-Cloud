import json
import time
from pqcrypto.sign.ml_dsa_65 import keygen, sign, verify

# ============================================================
# The Certificate Authority (CA) has its own long-term keypair
# ============================================================
print("=== CA: generate root keypair ===")
ca_public_key, ca_secret_key = keygen()
print("CA public key size:", len(ca_public_key), "bytes")
print()

# ============================================================
# The server (e.g. "example.com") generates its own keypair
# and asks the CA to certify it
# ============================================================
print("=== Server: generate its own keypair ===")
server_public_key, server_secret_key = keygen()
print()

# ============================================================
# CA builds and signs the certificate
# ============================================================
print("=== CA: issue certificate ===")
certificate_data = {
    "subject": "example.com",
    "issuer": "MyTestCA",
    "public_key": server_public_key.hex(),
    "algorithm": "ML-DSA-65",
    "issued_at": int(time.time()),
    "expires_at": int(time.time()) + 365 * 24 * 3600,
}

tampered_message = {
    "subject": "example.com",
    "issuer": "MyTestCA",
    "public_key": "tampered_public_key",
    "algorithm": "ML-DSA-65",
    "issued_at": int(time.time()),
    "expires_at": int(time.time()) + 365 * 24 * 3600,
}

#try to tamper or chnage the secret key from hacker by just a bit
# ca_secret_key_list = list(ca_secret_key)
# ca_secret_key_list[0] = 1
# ca_secret_key_invalid = bytes(ca_secret_key_list)

# The CA signs the exact bytes of the certificate data
cert_bytes = json.dumps(certificate_data, sort_keys=True).encode()
ca_signature = sign(ca_secret_key, cert_bytes)

certificate = {
    "data": certificate_data,
    "signature": ca_signature.hex(),
}

# cert_tampered = {
#     "data": tampered_message,
#     "signature": ca_signature_tampered.hex(),
# }

print("Certificate issued. CA signature size:", len(ca_signature), "bytes")
print()

# ============================================================
# A CLIENT verifying the certificate later
# (it independently has the CA's public key, e.g. baked into a browser)
# ============================================================
print("=== Client: verify the certificate ===")

# Re-derive the exact same bytes the CA signed
cert_data_to_check = json.dumps(certificate["data"], sort_keys=True).encode()
sig_to_check = bytes.fromhex(certificate["signature"])


# cert_data_to_check_tampered = json.dumps(cert_tampered["data"], sort_keys=True).encode()
# sig_to_check_tampered = bytes.fromhex(cert_tampered["signature"])

try:
    verify(ca_public_key, cert_data_to_check, sig_to_check)
    print("Certificate is VALID — this really was issued by the trusted CA.")
except Exception:
    print("Certificate is INVALID — reject this connection!")

# try:
#     verify(ca_public_key, cert_data_to_check_tampered, sig_to_check_tampered)
#     print("Certificate is VALID — this really was issued by the trusted CA.")
# except Exception:
#     print("Certificate is INVALID — reject this connection!")

# ============================================================
# Total size cost of this certificate
# ============================================================
total_size = len(json.dumps(certificate).encode())
print()
print("Total certificate size:", total_size, "bytes")