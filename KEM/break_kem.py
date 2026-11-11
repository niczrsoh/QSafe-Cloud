from pqcrypto.kem.ml_kem_768 import keygen, encaps, decaps

public_key, secret_key = keygen()
ciphertext, shared_secret_client = encaps(public_key)

# Attacker/network glitch flips one byte in the ciphertext
tampered_ciphertext = bytearray(ciphertext)
tampered_ciphertext[0] ^= 0xFF  # flip all bits in the first byte
tampered_ciphertext = bytes(tampered_ciphertext)

shared_secret_server_normal = decaps(secret_key, ciphertext)
shared_secret_server_tampered = decaps(secret_key, tampered_ciphertext)

print("Client's shared secret:      ", shared_secret_client.hex())
print("Server's secret (untampered):", shared_secret_server_normal.hex())
print("Server's secret (tampered):  ", shared_secret_server_tampered.hex())
print()
print("Untampered matches client?", shared_secret_server_normal == shared_secret_client)
print("Tampered matches client?  ", shared_secret_server_tampered == shared_secret_client)