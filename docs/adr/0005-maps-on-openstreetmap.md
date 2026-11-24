# 0005. Maps on OpenStreetMap, no Google Maps

## Context

The app shows listings and places on a map and lets people pick a location. Google Maps needs a billing account and charges for geocoding and places.

## Decision

- The backend needs no map provider: locations are PostGIS points and distances are computed in the database.
- The mobile app uses MapLibre with OpenStreetMap vector tiles from OpenFreeMap (free, no key).
- A location is chosen by dropping a pin; the backend stores the coordinates and the district. No geocoding at first; if needed, Nominatim within its usage policy.

## Consequences

- No map costs and no API key.
- OpenStreetMap's coverage of Sadat City streets is thinner than Google's; districts and landmarks come from our own data.
- Switching to another tile provider later is a configuration change in the app.
