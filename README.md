# wp-misc

This repository contains miscellaneous tools and modifications for WordPress development and performance analysis.

## Contents

1. **WordPress Hook Performance Analyzer**
   - File: `class-wp-hook.php.modified`
   - Description: A modified version of WordPress' WP_Hook class that includes performance measurement capabilities.

2. **TTFB Measurement Script**
   - File: `curl-ttfb`
   - Description: A Python script to measure Time to First Byte (TTFB) using curl, with optional DNS override.

## WordPress Hook Performance Analyzer

### Features
- Measures execution time and memory usage of WordPress hooks
- Provides detailed performance summaries
- Allows for selective measurement of specific hooks

### Usage
1. Replace the original `class-wp-hook.php` in your WordPress installation with the modified version.
2. To enable hook measurement, add `?measure_hooks=abcd` to your WordPress URL.
3. View the performance summary in the HTML comments of the page source.

## TTFB Measurement Script

### Features
- Measures Time to First Byte (TTFB) for a given URL
- Supports multiple requests for statistical analysis
- Provides min, max, average, and percentile statistics
- Offers DNS override option for local testing

### Usage
```
python3 curl-ttfb <url> <num_requests> [--disable-dns-override]
```

Example:
```
python3 curl-ttfb https://example.com 100
```

## Contributing

Contributions to improve these tools or add new WordPress-related utilities are welcome. Please submit a pull request or open an issue to discuss proposed changes.

