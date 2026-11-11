from pqcrypto.sign.ml_dsa_65 import keygen, sign, verify

message = b"User A send 100 ETH to User B"

print("=== Step 1: KeyGen ===")
public_key, secret_key = keygen()
print("Signer generates a keypair.")
print("Public key (first 20 bytes shown):", public_key[:20].hex())
print()

print("=== Step 2: Sign ===")
signature = sign(secret_key, message)
print("Message:", message)
print("Signature (first 20 bytes shown):", signature[:20].hex())
print("Signature length:", len(signature), "bytes")
print()

print("=== Step 3: Verify ===")
try:
    verify(public_key, message, signature)
    print("Verification result: VALID — signature checks out.")
except Exception as e:
    print("Verification result: INVALID —", e)