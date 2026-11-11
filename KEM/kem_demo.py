from pqcrypto.kem.ml_kem_768 import keygen, encaps, decaps

print("=== Step 1: KeyGen (server side) ===")
public_key, secret_key = keygen()
print("Server generates a keypair.")
print("Public key (first 20 bytes shown):", public_key[:20].hex())
print()

print("=== Step 2: Encapsulate (client side) ===")
ciphertext, shared_secret_client = encaps(public_key)
print("Client uses the server public key to produce a ciphertext + shared secret.")
print("Ciphertext (first 20 bytes shown):", ciphertext[:20].hex())
print("Client shared secret:", shared_secret_client.hex())
print()

print("=== Step 3: Decapsulate (server side) ===")
shared_secret_server = decaps(secret_key, ciphertext)
print("Server uses its private key to recover the shared secret from the ciphertext.")
print("Server shared secret:", shared_secret_server.hex())
print()

print("=== Check ===")
print("Do both sides have the same secret?", shared_secret_client == shared_secret_server)