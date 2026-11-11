from pqcrypto.kem.ml_kem_768 import keygen, encaps

public_key, secret_key = keygen()

# Call encaps() TWICE with the exact same public key
ciphertext1, secret1 = encaps(public_key)
ciphertext2, secret2 = encaps(public_key)

print("Ciphertext 1:", ciphertext1[:16].hex())
print("Ciphertext 2:", ciphertext2[:16].hex())
print()
print("Secret 1:", secret1.hex())
print("Secret 2:", secret2.hex())
print()
print("Are the secrets the same?", secret1 == secret2)