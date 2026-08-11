# Parcel Delivery Management System

Native PHP + MySQL parcel management system with admin and rider roles, GPS tracking, parcel lifecycle updates, proof photo uploads, and AJAX-driven UI updates.

## Setup

1. Import `sql/parcel_delivery.sql` into MySQL.
2. Edit `config/config.php` if your MySQL credentials differ.
3. Open `http://localhost/Parcel_Deliver/install.php` and create the first admin account.
4. Log in from `index.php?page=login`.

## Notes

- GPS tracking relies on browser geolocation while the rider page stays open.
- Google Maps Places autocomplete is wired to load when `GOOGLE_MAPS_API_KEY` is defined in `config/config.php`.
- Leaflet is used for map rendering with OpenStreetMap tiles.
