# 0003. Sign-in with a one-time code and Sanctum tokens

## Context

Residents are used to mobile numbers, not passwords. The app needs a token per device.

## Decision

Users sign in with an Egyptian mobile number and a 4-to-6-digit code sent by SMS. Codes are hashed, expire after a few minutes, allow few attempts, and are rate limited per number and per IP. A verified code issues a Sanctum personal access token named after the device. The SMS provider sits behind an interface with a log driver for local development and tests. Admins sign in to Filament with email, password and two-factor authentication.

## Consequences

- No password reset flow for app users.
- Every sign-in costs one SMS; the rate limits also protect that cost.
- Changing the SMS provider is a new driver, not a change to the sign-in flow.
