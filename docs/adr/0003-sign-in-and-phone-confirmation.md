# 0003. Sign-in with Google and Apple, phone confirmed on WhatsApp, Sanctum tokens

## Context

Residents are used to mobile numbers, not passwords. The app needs a token per device. The owner does not want to pay for SMS at first, and an Egyptian SMS sender name takes time to register.

## Decision

- Residents sign in with Google or Apple. The backend verifies the provider's ID token and finds or creates the user. Apple is required on iOS whenever Google is offered.
- The phone number is confirmed separately, because advertisers and craftsmen must be reachable. Confirmation sits behind a `PhoneVerifier` interface:
    - `log` driver for development and tests: the code is written to the log;
    - `whatsapp_inbound` driver for launch: the app opens WhatsApp with a pre-filled message containing a one-time code to our business number; our webhook receives it, matches the sender's number and the code, and confirms the number. Conversations a customer starts are free on the WhatsApp Cloud API at the time of writing;
    - an SMS driver can be added later without changing the flow.
- Codes are hashed, expire after a few minutes, allow few attempts, and are rate limited per number and per IP.
- A signed-in user receives a Sanctum personal access token named after the device.
- Admins sign in to Filament with email, password and two-factor authentication.

## Consequences

- No SMS cost and no sender registration before launch.
- A resident without a Google or Apple account cannot sign in until another method (email code, SMS) is added.
- The WhatsApp driver needs the Meta setup the owner already knows from SellBubble, and its webhook follows the usual rules: verify the signature, store, queue, return 200.
