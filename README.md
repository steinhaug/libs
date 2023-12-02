# steinhaug_libs v1 og v2

Core class for serving content to browser, uses GZIP compression.

## VERSION

v2.0.0 - Added v2 class as steinhaug_libs, renamed v1 for steinhaug_libs_v1
v1.0.1 - PS4 file structure in place, added missing functions
v1.0.0 - Initial state

## USAGE

**Syntax:**

    $swlib = new steinhaug_libs_v1;
    $swlib->start_ob(false, true, 'gzip');
    // page
    $swlib->end_ob('text/html');  

See ./public_html/ for examples.

## INSTALLATION

Install the [composer package](https://packagist.org/packages/steinhaug/libs):

    > composer require steinhaug/libs

Or download the [latest release](https://github.com/steinhaug/libs/releases/latest) and include src/steinhaug/libs.php.

## AUTHORS

[Kim Steinhaug](https://github.com/steinhaug) \([@steinhaug](https://twitter.com/steinhaug)\)

## LICENSE

This library is released under the MIT license.

## Feel generous?

Buy me a beer, [donate](https://steinhaug.com/donate/).