import hashlib
from cryptography.hazmat.primitives.asymmetric.x25519 import X25519PrivateKey
from pqcrypto.kem.ml_kem_768 import keygen, encaps, decaps

# ============================================================
# SERVER: generates BOTH a classical and a post-quantum keypair
# ============================================================
print("=== Server: KeyGen (both algorithms) ===")
server_x25519_private = X25519PrivateKey.generate()
server_x25519_public = server_x25519_private.public_key()

server_mlkem_public, server_mlkem_secret = keygen()
print("Server generated an X25519 keypair AND an ML-KEM-768 keypair.")
print()

# ============================================================
# CLIENT: uses server's public keys to derive both secrets
# ============================================================
print("=== Client: derive both secrets ===")

# Classical side: client makes its own X25519 keypair, does the DH computation
client_x25519_private = X25519PrivateKey.generate()
client_x25519_public = client_x25519_private.public_key()
client_x25519_secret = client_x25519_private.exchange(server_x25519_public)

# PQC side: client encapsulates using server's ML-KEM public key
mlkem_ciphertext, client_mlkem_secret = encaps(server_mlkem_public)

print("Client X25519 secret:", client_x25519_secret.hex())
print("Client ML-KEM secret:", client_mlkem_secret.hex())
print()

# ============================================================
# CLIENT: combine both secrets into the final session key
# ============================================================
client_combined = client_x25519_secret + client_mlkem_secret
client_final_key = hashlib.sha256(client_combined).hexdigest()
print("Client final hybrid session key:", client_final_key)
print()

# ============================================================
# SERVER: does the matching computations to arrive at the same key
# ============================================================
print("=== Server: derive both secrets, using client's public parts ===")
server_x25519_secret = server_x25519_private.exchange(client_x25519_public)
server_mlkem_secret_recovered = decaps(server_mlkem_secret, mlkem_ciphertext)

server_combined = server_x25519_secret + server_mlkem_secret_recovered
server_final_key = hashlib.sha256(server_combined).hexdigest()
print("Server final hybrid session key:", server_final_key)
print()

# ============================================================
# CHECK
# ============================================================
print("=== Check ===")
print("Do both sides arrive at the same final key?", client_final_key == server_final_key)