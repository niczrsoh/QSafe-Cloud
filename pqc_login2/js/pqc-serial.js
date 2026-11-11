/**
 * Shared Key Web Serial helpers.
 * New protocol: REGISTER_MLKEM -> DEVICE_ID + MLKEM_PK_B64.
 * Legacy protocol remains supported for already flashed challenge-response devices.
 */
(function (global) {
    'use strict';

    function parseEsp32SerialBuffer(buffer) {
        let key = '';
        let signature = '';
        let proof = '';
        let mlkemPublicKeyB64 = '';
        let algorithm = '';
        const lines = buffer.split(/\r?\n/);

        for (const raw of lines) {
            const line = raw.trim();
            if (line.startsWith('DEVICE_ID:')) {
                const hex = line.slice(10).replace(/[^a-fA-F0-9]/g, '');
                if (hex.length === 64) key = hex.toLowerCase();
            }
            if (line.startsWith('PQC_ID:')) {
                const hex = line.slice(7).replace(/[^a-fA-F0-9]/g, '');
                if (hex.length === 64) key = hex.toLowerCase();
            }
            if (line.startsWith('PQC_KEY:')) {
                const hex = line.slice(8).replace(/[^a-fA-F0-9]/g, '');
                if (hex.length === 64) key = hex.toLowerCase();
            }
            if (line.startsWith('PQC_SIG:')) {
                const hex = line.slice(8).replace(/[^a-fA-F0-9]/g, '');
                if (hex.length === 64) signature = hex.toLowerCase();
            }
            if (line.startsWith('MLKEM_PROOF:')) {
                const hex = line.slice(13).replace(/[^a-fA-F0-9]/g, '');
                if (hex.length === 64) proof = hex.toLowerCase();
            }
            if (line.startsWith('MLKEM_ALG:')) {
                algorithm = line.slice(10).trim();
            }
            if (line.startsWith('MLKEM_PK_B64:')) {
                const b64 = line.slice(13).replace(/\s+/g, '');
                if (b64.length > 1000) mlkemPublicKeyB64 = b64;
            }
        }

        return { key, signature, proof, mlkemPublicKeyB64, algorithm };
    }

    async function closePort(port) {
        if (!port) return;
        try { await port.close(); } catch (e) { /* ignore */ }
    }

    async function writeLine(port, line) {
        const writer = port.writable.getWriter();
        await writer.write(new TextEncoder().encode(line + '\n'));
        writer.releaseLock();
    }

    async function readUntil(port, predicate, timeoutMs) {
        const reader = port.readable.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let released = false;
        const timeout = setTimeout(() => reader.cancel().catch(() => {}), timeoutMs);

        try {
            while (true) {
                const { value, done } = await reader.read();
                if (done) break;
                buffer += decoder.decode(value, { stream: true });
                const parsed = parseEsp32SerialBuffer(buffer);
                if (predicate(parsed, buffer)) {
                    clearTimeout(timeout);
                    reader.releaseLock();
                    released = true;
                    return { parsed, buffer };
                }
            }
        } finally {
            clearTimeout(timeout);
            if (!released) {
                try { reader.releaseLock(); } catch (e) { /* ignore */ }
            }
        }
        return { parsed: parseEsp32SerialBuffer(buffer), buffer };
    }

    /**
     * @param {object} opts
     * @param {function(string): void} [opts.onStatus]
     * @param {boolean} [opts.requireSignature]
     * @param {number} [opts.bootDelayMs]
     * @param {number} [opts.timeoutMs]
     */
    async function bindEsp32Device(opts) {
        const onStatus = opts.onStatus || function () {};
        const requireSignature = !!opts.requireSignature;
        const bootDelayMs = opts.bootDelayMs ?? 1500;
        const timeoutMs = opts.timeoutMs ?? 30000;

        if (!('serial' in navigator)) {
            throw new Error('Your browser does not support Web Serial. Please use Chrome or Edge on desktop.');
        }

        onStatus('Select your Key device...');
        let port;
        try {
            port = await navigator.serial.requestPort();
        } catch (portErr) {
            if (portErr.name === 'NotFoundError') {
                throw new Error('No device selected. Please select your Key and try again.');
            }
            throw portErr;
        }

        onStatus('Opening secure channel...');
        await port.open({ baudRate: 115200 });

        try {
            onStatus('Waiting for device boot...');
            await new Promise((resolve) => setTimeout(resolve, bootDelayMs));

            onStatus('Requesting ML-KEM public key...');
            await writeLine(port, 'REGISTER_MLKEM');
            await new Promise((resolve) => setTimeout(resolve, 300));
            await writeLine(port, 'REGISTER_MLKEM');

            let result = await readUntil(
                port,
                (parsed) => parsed.key.length === 64 && parsed.mlkemPublicKeyB64.length > 1000,
                Math.min(timeoutMs, 3000)
            );

            if (result.parsed.key && result.parsed.mlkemPublicKeyB64) {
                await closePort(port);
                return {
                    key: result.parsed.key,
                    mlkemPublicKeyB64: result.parsed.mlkemPublicKeyB64,
                    algorithm: result.parsed.algorithm || 'ML-KEM-512',
                    mode: 'mlkem512',
                    port: null,
                };
            }

            if (requireSignature) {
                throw new Error('Device did not return ML-KEM registration data. Flash the ML-KEM firmware first.');
            }

            onStatus('ML-KEM key not detected. Falling back to legacy device ID...');
            await writeLine(port, 'BIND');
            result = await readUntil(port, (parsed) => parsed.key.length === 64, timeoutMs);
            await closePort(port);

            if (!result.parsed.key) {
                throw new Error('No device ID received. Check USB connection and try again.');
            }
            return { key: result.parsed.key, signature: result.parsed.signature, mode: 'legacy', port: null };
        } catch (err) {
            await closePort(port);
            throw err;
        }
    }

    global.PQCSerial = {
        parseEsp32SerialBuffer,
        bindEsp32Device,
        closePort,
    };
})(typeof window !== 'undefined' ? window : this);
