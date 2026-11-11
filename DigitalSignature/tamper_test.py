from pqcrypto.sign.ml_dsa_65 import keygen, sign, verify

message = b"User A send 100 ETH to User B"
public_key, secret_key = keygen()
signature = sign(secret_key, message)

# Attacker tampers with the message after signing
tampered_message = b"User A send 999 ETH to User B"

print("Original message verification:")
try:
    verify(public_key, message, signature)
    print("  VALID")
except Exception:
    print("  INVALID")

print("Tampered message verification (same signature, different message):")
try:
    verify(public_key, tampered_message, signature)
    print("  VALID")
except Exception:
    print("  INVALID")