codebird-cors-proxy
===================
*Proxy to the Twitter API, adding CORS headers to replies.*

Copyright (C) 2013-2015 Jublo Solutions &lt;support@jublo.net&gt;

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.


This is the CORS proxy of the Codebird library.

### Codebird versions

- JavaScript: https://github.com/jublonet/codebird-js
- PHP: https://github.com/jublonet/codebird-php

### Requirements

- For use with **codebird-js 2.4.1 or higher**
- PHP 5.5.0 or higher
- CURL extension
- OpenSSL extension
- For IIS, [URL Rewrite](http://www.iis.net/downloads/microsoft/url-rewrite) is required
  - Uploading media requires the user account of your IIS Application Pool
    to have NTFS write permissions to the %TEMP% folder.

Set-up
------

To install the Codebird CORS proxy on your server:

1. Upload the folder to your webspace.
2. Rename the folder to “codebird-cors-proxy”.
3. In your Javascript, tell codebird-js to use the new proxy server:
    ```javascript
    cb.setProxy("https://example.com/codebird-cors-proxy/");
    ```

Notes
-----

- Never run your proxy on an unencrypted server connection ("http://").
  Using HTTPS is essential for your user’s security and privacy.
