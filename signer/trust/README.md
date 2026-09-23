# CA de confianza del verificador

`pipeline.verify_signed_pdf` confía en el almacén del sistema **más** los
certificados `.pem` / `.crt` / `.cer` de este directorio:

- Los certificados autofirmados (raíz) entran como *trust roots*.
- Los demás (intermedias) solo sirven para armar la cadena.

Con eso el campo `trusted` de `/sign` y `/verify` sale `true` para las firmas
hechas con un certificado de estas CA, aunque la CA no esté en el almacén del
sistema.

El directorio se puede reemplazar sin reconstruir la imagen montando otro en
su lugar y apuntando `SIGNER_TRUST_DIR` a él.

## Contenido

| Archivo | Sujeto | Vigencia | SHA-256 |
|---|---|---|---|
| `llamape-root-ca.pem` | CN=Llama.pe Root CA, O=LLAMA.PE, C=PE | 2018-07-18 → 2038-07-13 | `22:8C:A8:4D:00:68:7D:20:DF:E5:43:4A:7A:C9:82:E7:55:32:A8:14:66:01:89:6E:DC:D9:14:6E:54:A2:89:FE` |
| `llamape-sha256-standard-ca.pem` | CN=Llama.pe SHA256 Standard CA, O=LLAMA.PE, C=PE | 2018-07-18 → 2038-07-13 | `04:12:EB:96:2C:BE:52:AA:80:D2:62:B4:55:5D:3C:74:07:FF:ED:A2:8F:FD:0C:7D:F3:68:07:B4:D3:0A:6C:72` |

Llama.pe emite el certificado de firma de la plataforma (el titular es el
gerente general de OVERHEAD MEN S.A.C.). Cuando cambie de CA, se agrega aquí
la raíz nueva y se comprueba su huella contra la fuente oficial antes de
commitearla.
