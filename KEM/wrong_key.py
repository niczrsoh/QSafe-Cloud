from pqcrypto.kem.ml_kem_768 import keygen, encaps, decaps

# Two different people generate their own keypairs
alice_public, alice_secret = keygen()
mallory_public, mallory_secret = keygen()  # a completely unrelated keypair

ciphertext, shared_secret_alice = encaps(alice_public)

# Mallory intercepts the ciphertext and tries decapsulating with HER private key
mallory_result = decaps(mallory_secret, ciphertext)

print("Alice's real shared secret:", shared_secret_alice.hex())
print("Mallory's guess:           ", mallory_result.hex())
print("Did Mallory get the real secret?", mallory_result == shared_secret_alice)