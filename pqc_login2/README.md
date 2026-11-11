A. What is this?
This is a QSafe Cloud, a continuous and quantum resistant cloud authentication system. 
Problems??
Most of the current cloud platform still using traditional public key cryptography such as RSA or ECDSA. These cryptographic algorithms are vulnerable to attacks from quantum computers.
Furthermore, cloud platform still using conventional multi-factor authentication such as SMS, software keys and etc. So, it just provides 1 time login. When the user walks away with his laptop, the cloud platform is fundamentally vulnerable to session hijacking.
Solution?
Here, we address these gaps by integrating PQC with hardware-based PUF (Physical Unclonable Functions) to provide a quantum-resistant and continuous authentication system. The PQC keys are generated and stored in the PUF, which is unique to each device. This allows for a continuous authentication process where the user's identity is continuously verified through the PUF.
During the login process,
there are 2 stages, first the user have to login using the credentials.
Then the system will prompt the user to login with the device key. Once, the user plug in the device key, the web browser key Serial API will issue the challenge-response to the device key. The device key will then use the ML-KEM private key to generate the response to verify the identity binding. 
After that, the system will go directly to the dashboard. The web interface will keep maintain the background session hearbeat. So, if the user walks away from his laptop with the device key, the session will auto terminated. 
So if the key is lost, there are few recovery process 
1. Key Generation and Share Distribution - share1 store in server, share2 asks user to write down, share3- user download the recovery file, so if 2 of 3 shares are required for the recovery process
2. Knowledge-Based Recovery (KBA). during registration, it will ask user the question and user have to provide the answer. So eventhough if the laptop and key was stolen, the attacker still cant access the account unless they know the answer to the question. so, the user can still have time to freeze the account via admin
3. Once recovery, the user can choose to bind with new esp32 or freeze the account

B. Run in docker:
You run that command in your Mac Terminal (the macOS Terminal or iTerm 
app).

Your nextcloud-local Docker container is already running on localhost:8080, but there is one quick step needed before app:enable will work:

Nextcloud must finish its one-time initialization first (otherwise it displays "Nextcloud is not installed - There are no commands defined in the 'app' namespace").

Step 1: Initialize Nextcloud

Run this command in your Mac Terminal:

bash
docker exec -u www-data nextcloud-local php occ maintenance:install --admin-user "admin" --admin-pass "admin12345"

(This completes the SQLite database setup and creates your admin account in ~5 seconds).

Step 2: Enable the pqc_login App

Now run the enable command in your Mac Terminal:

bash
docker exec -u www-data nextcloud-local php occ app:enable pqc_login

It will output:

text
pqc_login enabled
Step 3: Open in Chrome

Once enabled, open Chrome and access your local instance:

Registration & Key Binding:
http://localhost:8080/index.php/apps/pqc_login/register
Login:
http://localhost:8080/login