# Open questions

Questions waiting on the owner, and known issues not fixed yet.

1. The commission rate and the brokerage agreement's terms (needed by step 4.3).
2. Prices of featured packages (needed by step 4.1).
3. Sadat City's full list of districts, with a centre for each (DECISIONS 43).
4. Known issue until step 2.6, which fixes it as the owner decided (DECISIONS 39): at the 0.25 threshold, search matches short common words (`منزل` matches every text with `من`), and the planner skips the GIN trigram index until the operator's cost is raised.
5. Sadat City's hospitals and transport lines for the city guide (DECISIONS 67).
6. The Google OAuth client ids (Android, iOS, web) and Apple's bundle id for `AWEZ_GOOGLE_CLIENT_IDS` and `AWEZ_APPLE_CLIENT_IDS` (BUILD_PLAN phase 0). Until then, every real ID token is refused and only the fake sign-in works, locally.
