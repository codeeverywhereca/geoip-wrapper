# geoip-wrapper
A PHP wrapper for MaxMind's GeoIP database

# Features
- No external dependencies
- Fast lookups
- Embedded database (SQLite)

# How It Works
The script converts the CSV files into an SQLite database and JSON files.
![example](graph.png)

Based on the following columns:

For GeoLite2-City-Blocks-IPv4.csv
- 0:network
- 1:geoname_id
- 2:registered_country_geoname_id
- 3:represented_country_geoname_id
- 4:is_anonymous_proxy
- 5:is_satellite_provider
- 6:postal_code
- 7:latitude
- 8:longitude

For GeoLite2-City-Locations-en.csv
- 0:geoname_id, *
- 1:locale_code, *
- 2:continent_code, *
- 3:continent_name, *
- 4:country_iso_code, *
- 5:country_name, *
- subdivision_1_iso_code,
- subdivision_1_name,
- subdivision_2_iso_code,
- subdivision_2_name,
- 10:city_name, *
- metro_code,
- 12:time_zone *

# How To Use
Download the CSV files from MaxMind's website ( http://dev.maxmind.com/geoip/geoip2/geolite2/ ), run $geoip->build() to create the SQLite database, then use $geoip->get(IP_ADDR) in your website / app.

Running the build script
```php
$geoip = new geoip();
$geoip->build();
```

Doing a lookup
```php
$geoip = new geoip();
$loc = $geoip->get("8.8.8.8");
echo json_encode($loc, JSON_PRETTY_PRINT);
```

# License
MIT
