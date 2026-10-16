#!/bin/sh
# Activa la firma digital en el stack de entrega para las capturas del manual.
#
# La demo arranca con la firma digital desactivada y sin certificados, así que
# sin este paso ninguna captura enseña la firma automática de la empresa
# (sello, tarjeta "Firmado por la empresa", certificados por empresa).
#
# Genera DENTRO del contenedor dos certificados autofirmados con datos
# ficticios (global de la plataforma y propio de Corporación ABC), los carga,
# activa la firma y encola la firma de todas las boletas de la demo. Ni las
# llaves ni los .pfx salen del contenedor: no se versionan ni se entregan.
#
# Uso (con el stack de entrega levantado), antes de capture.js:
#   sh docs/screenshots/preparar-firma-demo.sh
set -e

APP="${APP_CONTAINER:-miboleta_entrega-app-1}"

docker exec "$APP" sh -c '
set -e
mkdir -p /tmp/certs && cd /tmp/certs
openssl req -x509 -newkey rsa:2048 -nodes -days 365 -keyout g.key -out g.crt \
  -subj "/C=PE/ST=Lima/O=MiBoleta Plataforma Demo S.A.C./title=Representante Legal/serialNumber=RUC20999999991/CN=Ana Torres Salinas" 2>/dev/null
openssl pkcs12 -export -inkey g.key -in g.crt -out global-demo.pfx -passout pass:demo1234
openssl req -x509 -newkey rsa:2048 -nodes -days 365 -keyout e.key -out e.crt -utf8 \
  -subj "/C=PE/ST=Lima/O=Corporación ABC S.A.C./title=Gerente General/serialNumber=RUC20123456789/CN=Carlos Mendoza Ríos" 2>/dev/null
openssl pkcs12 -export -inkey e.key -in e.crt -out abc-demo.pfx -passout pass:demo1234

cat > /tmp/certs/cargar.php <<"PHP"
<?php
use Illuminate\Http\UploadedFile;
$root = App\Models\User::all()->first(fn ($u) => $u->isRoot());
$svc = app(App\Services\SignatureCertificateService::class);
$svc->storeCertificate($root, new UploadedFile("/tmp/certs/global-demo.pfx", "global-demo.pfx", "application/x-pkcs12", null, true), "demo1234");
$svc->enableSignature($root, true);
$abc = App\Models\Tenant::where("ruc", "20123456789")->first();
$svc->storeTenantCertificate($root, $abc, new UploadedFile("/tmp/certs/abc-demo.pfx", "abc-demo.pfx", "application/x-pkcs12", null, true), "demo1234");
$firma = app(App\Services\DocumentSigningService::class);
foreach (App\Models\Document::withoutGlobalScopes()->get() as $d) {
    if (!$d->isOrphan()) {
        $firma->markPendingAndDispatch($d);
    }
}
echo "Firma digital activada; boletas encoladas para firmar.\n";
PHP
php artisan tinker /tmp/certs/cargar.php
'

echo "Esperando a que Horizon firme las boletas..."
for _ in $(seq 1 40); do
  n=$(docker exec "$APP" php artisan tinker --execute 'echo App\Models\Document::withoutGlobalScopes()->where("digital_signature_status","pending")->count();' 2>/dev/null | tr -dc 0-9)
  [ "$n" = "0" ] && { echo "Listo."; exit 0; }
  sleep 3
done
echo "Aún quedan boletas sin firmar; revisa los logs de horizon." >&2
exit 1
