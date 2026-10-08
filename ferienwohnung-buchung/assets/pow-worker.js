'use strict';
self.onmessage = async ({ data }) => {
  try {
    if (!self.crypto?.subtle) throw new Error('Dein Browser unterstützt den Spam-Schutz nicht. Bitte einen aktuellen Browser mit HTTPS verwenden.');
    const encoder = new TextEncoder();
    for (let nonce = 0; nonce < 2000000; nonce++) {
      const digest = new Uint8Array(await crypto.subtle.digest('SHA-256', encoder.encode(data.challenge + ':' + nonce)));
      const hex = Array.from(digest, b => b.toString(16).padStart(2, '0')).join('');
      if (hex.startsWith('0'.repeat(data.difficulty))) { self.postMessage({ nonce: String(nonce) }); return; }
    }
    throw new Error('Spam-Schutz bitte erneut versuchen.');
  } catch (e) { self.postMessage({ error: e.message }); }
};
