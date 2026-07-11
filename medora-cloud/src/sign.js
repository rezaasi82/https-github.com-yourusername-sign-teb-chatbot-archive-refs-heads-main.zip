/**
 * HMAC signing / verification — mirrors the plugin's SWC_Cloud_Client:
 * header `X-Medora-Sign: sha256=<hex(hmac_sha256(rawBody, secret))>`.
 */
import crypto from 'node:crypto';

export function sign(body, secret) {
  return 'sha256=' + crypto.createHmac('sha256', secret).update(body).digest('hex');
}

/** Timing-safe verification of an inbound signature header. */
export function verify(rawBody, header, secret) {
  if (!secret) return true;            // no secret configured => accept (dev only)
  if (!header) return false;
  const expected = sign(rawBody, secret);
  const a = Buffer.from(expected);
  const b = Buffer.from(String(header));
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

/** Detached signature over a JSON object (for signed license responses). */
export function signObject(obj, secret) {
  return crypto.createHmac('sha256', secret).update(JSON.stringify(obj)).digest('hex');
}

/** Reject stale requests (replay protection) — timestamp within `skew` seconds. */
export function freshTimestamp(header, skew = 300) {
  const t = parseInt(String(header || ''), 10);
  if (!Number.isFinite(t)) return true; // header optional
  return Math.abs(Date.now() / 1000 - t) <= skew;
}
