<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="MiBoleta API",
 *     version="1.0.0",
 *     description="API para gestión de documentos y vacaciones",
 *     @OA\Contact(
 *         email="admin@miboleta.com"
 *     )
 * )
 * 
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="API Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Token de autenticación Sanctum"
 * )
 */
abstract class Controller
{
    //
}
