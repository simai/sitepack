# Encrypted envelope

This example contains only the public header `example.sitepack.enc.json`.
The binary file `example.sitepack.enc` is not included.

Age decryption:
```
age -d example.sitepack.enc > output.sitepack
```
After decryption, `output.sitepack` must be validated as a regular SitePack package.
