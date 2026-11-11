## Foundation of modern cryptography

1. First, understand this one picture

Imagine you want to send this:

"I love cats."

You don't want a stranger to read it.

So you use encryption:

Original message
"I love cats."
       ↓
   ENCRYPTION
       ↓
"X7#kP91@..."
       ↓
    Internet
       ↓
"X7#kP91@..."
       ↓
   DECRYPTION
       ↓
"I love cats."

There are two important things here:

Algorithm

The method used to lock/unlock the message.

Key

The secret value needed to actually unlock it.

Think:

Algorithm = type of lock
Key       = your actual key

This is the foundation for almost everything in the lesson.

2. "The burglar already owns your lock"

This sounds complicated, but it's actually a very simple idea.

Imagine you have a padlock:

🔒 Lock: Brand XYZ

You might think:

"Nobody should know how my lock works!"

Cryptography says:

Assume the attacker knows exactly how your lock works.

They can buy the same lock.

They can read the manual.

They can inspect the source code.

They know the algorithm.

But they don't have your key.

So:

Attacker knows:
✓ Algorithm
✓ How encryption works
✓ Source code
✓ Mathematical design

Attacker does NOT know:
✗ Secret key

And your data should still be secure.

That's Kerckhoffs's principle.

3. Why is this important?

Suppose I create a secret encryption algorithm:

MY SUPER SECRET ALGORITHM
Nobody knows how it works!

I say:

"Your data is safe because nobody knows my algorithm."

That's bad security.

Eventually somebody may:

reverse engineer it
steal the source code
publish the algorithm
discover a weakness

Then suddenly:

SECRET ALGORITHM EXPOSED
        ↓
    EVERYTHING BROKEN

Instead, good cryptography says:

PUBLIC ALGORITHM
        +
SECRET KEY
        ↓
     SECURITY

For example, AES is completely public.

People know exactly how AES works.

Researchers have spent decades attacking it.

Yet your AES-encrypted data can still be secure because the key is secret.

4. Now come to CIA thing for a moment

The CIA triad is simply asking:

"What does it mean for information to be secure?"

There are three basic answers.

C — Confidentiality

Nobody unauthorized can read it.

Example:

You send me:

"My bank password is ABC123."

Encryption protects confidentiality.

You → 🔒 → Internet → Me

A hacker intercepts it and sees:

X7@92kL...

They can't read the original message.

I — Integrity

Nobody can secretly change it.

Imagine:

Original:
"Transfer RM100 to Ali"

Hacker changes it to:

"Transfer RM10,000 to Ali"

That's an integrity problem.

Digital signatures and other mechanisms can help detect unauthorized changes.

A — Availability

You can actually access the system/data when you need it.

For example:

You try to access your bank:

LOGIN
  ↓
SERVER DOWN ❌

Your information may still be confidential.

It may still have integrity.

But it's not available.

So remember:

Letter	Simple meaning	Question
C	Confidentiality	"Can someone read it?"
I	Integrity	"Can someone change it?"
A	Availability	"Can I access it?"

That's it.

Don't overthink CIA yet.

5. Now we reach the REALLY important distinction

The lesson talks about:

Information-theoretic security

and

Computational security

This sounds scary.

It isn't.

Think about two locks.

Lock A

A lock that is:

Mathematically impossible to open without the key.

Even if the attacker has:

1,000 computers
1 billion computers
∞ computers
∞ years

They still cannot determine the original message.

That's roughly the idea of information-theoretic security.

Lock B

A lock that can theoretically be broken, but it would take an absurd amount of computing.

For example:

"It would take 10²⁰ years."

That's computational security.

It's not mathematically impossible.

It's just:

Too difficult to be practical.

6. The One-Time Pad

The lesson then introduces the one-time pad.

Don't worry about the mathematical formula yet.

Just remember:

One-time pad = extremely powerful encryption using a truly random secret key that is as long as the message.

Suppose your message is:

HELLO

You need a random key of the same length:

Message: H E L L O
Key:     X Q A Z M

You combine them.

You get:

Ciphertext: ?????

Someone sees the ciphertext.

Even with unlimited computing power, they cannot determine which plaintext was actually intended.

That's why it provides information-theoretic security.

7. But there's a huge problem

Imagine you want to encrypt a 1 GB video.

One-time pad says:

"Okay. Give me a 1 GB truly random secret key."

Then you need to secretly deliver that 1 GB key to the receiver.

And for the next video:

Another 1 GB key.

And another:

Another 1 GB key.

And you can never reuse the key.

That's horrible for the Internet.

So:

One-time pad
     ↓
Extremely secure
     ↓
But extremely difficult to manage

That's why normal computers don't use one-time pads for everything.

8. Enter AES

Instead of:

1 GB message
+
1 GB secret key

AES can do something more practical:

1 GB message
+
16-byte / 32-byte secret key

Much easier.

But now we have made a trade:

One-time pad
Security:
Mathematically guaranteed

Key:
Huge
AES
Security:
Based on computational difficulty

Key:
Small

So AES is not "mathematically impossible to break."

Instead:

We believe breaking AES is computationally infeasible.

That's an extremely important cybersecurity concept.

9. This is where quantum computing enters 

Now you're finally reaching the reason this lesson matters to post-quantum cryptography.

Today we use many algorithms whose security depends on mathematical problems being difficult.

For example:

RSA
ECDSA
Elliptic Curve Cryptography

We believe:

"A normal computer can't solve these problems efficiently."

So:

Mathematical problem
        ↓
Very difficult
        ↓
Attacker can't practically solve it
        ↓
SECURE

But then comes the quantum computer.

Certain quantum algorithms, especially Shor's algorithm, can efficiently solve some of the mathematical problems behind:

RSA
ECC
ECDSA

So the problem isn't:

"Cryptography suddenly stopped working."

The problem is:

The attack became computationally feasible.

10. This is why Post-Quantum Cryptography exists

This is probably the most important connection for you.

Before quantum computers:

ECDSA
  ↓
Hard mathematical problem
  ↓
Classical computer can't efficiently solve it
  ↓
Secure

With a sufficiently powerful quantum computer:

ECDSA
  ↓
Shor's algorithm
  ↓
Problem becomes efficiently solvable
  ↓
ECDSA security breaks

So researchers look for different mathematical problems.

For example:

Lattice problems
      ↓
ML-DSA
ML-KEM
      ↓
Believed resistant to quantum attacks

This is what post-quantum cryptography (PQC) is about.

11. One important correction to the lesson

There is one sentence you should mentally adjust:

"The one-time pad is the only cipher proven unbreakable."

The intended meaning is that the one-time pad achieves perfect secrecy under its assumptions. But don't take "unbreakable" to mean "automatically secure in every real-world implementation." Key generation, distribution, storage, endpoint security, and correct use still matter.

For learning the fundamentals, though, you can remember:

One-time pad = perfect/information-theoretic secrecy.

12. H(K) ≥ H(M) 

This is Shannon's condition for perfect secrecy.

To understand entropy mathematically,
just translate it into English:

For perfect secrecy, the key needs enough randomness to cover the uncertainty in the message.

For the one-time pad, that essentially means:

Message length ≈ Key length

So:

100 MB message
      ↓
≈ 100 MB random key

That's the important intuition.


13. The entire lesson in ONE diagram

If you remember nothing else, remember this:

                    CYBERSECURITY
                         │
                         ↓
                What are we protecting?
                         │
             ┌───────────┼───────────┐
             ↓           ↓           ↓
        Confidentiality Integrity Availability
          "read?"       "change?"    "access?"
             │
             ↓
        CRYPTOGRAPHY
             │
             ↓
     ┌───────┴────────┐
     ↓                ↓
  Encryption       Signatures
     ↓                ↓
  Secrecy          Integrity
     │
     ↓
 ┌───┴────────────────────────┐
 ↓                            ↓
Information-              Computational
theoretic security        security
 ↓                            ↓
OTP                          AES
 ↓                            RSA
Perfect secrecy              ECDSA
                             ML-KEM
                             ML-DSA
                               ↓
                     "Hard to break"
                               ↓
                       Quantum computers
                               ↓
                  Some old algorithms break
                               ↓
                   POST-QUANTUM CRYPTOGRAPHY

14. The 8 words I want you to remember

Don't try to memorize the whole lesson.

For now, understand these:

Term	Your definition
Encryption - Turn readable data into unreadable data
Decryption - Turn it back
Algorithm - The mathematical method
Key - Secret value used by the algorithm
Confidentiality - Stop unauthorized reading
Integrity - Stop/detect unauthorized modification
Information-theoretic security - Secure even against unlimited computing power
Computational security - Secure because breaking it is practically too difficult

And then:

Kerckhoffs's principle: Assume the attacker knows the algorithm. Only the key needs to be secret.

One-time pad: Perfect secrecy, but impractical because the key must be as long as the message and never reused.

AES: Computationally secure encryption.

RSA/ECDSA: Computationally secure public-key cryptography that is vulnerable to sufficiently powerful quantum computers.

PQC: New cryptography designed to remain secure against known quantum attacks.
