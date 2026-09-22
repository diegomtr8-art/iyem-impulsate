# API v1 — Impúlsate (app móvil)

Contrato de la API que consume la app Expo. **Todos los ejemplos de respuesta son reales**: se copiaron de llamadas
hechas el 2026-09-22 contra la BD local (`php artisan impulsate:demo-movil`); las de la sección 12 contra Apache (`http://192.168.10.6/citas/public`). Donde un ejemplo se recortó
(listas largas), se indica debajo del bloque.

## Generalidades

| | |
|---|---|
| URL base (local) | `http://localhost/citas/public/api/v1` |
| URL base (producción) | `https://<dominio>/api/v1` |
| Formato | JSON. Enviar siempre `Accept: application/json`. Subidas de archivos: `multipart/form-data`. |
| Autenticación | `Authorization: Bearer <token>` (Sanctum). El token se obtiene en `POST /auth/login` y caduca a los **30 días**. |
| Fechas | ISO 8601 con zona (`2026-10-06T10:00:00-06:00`). Fechas sin hora: `YYYY-MM-DD`. |
| Idioma | Mensajes en español (salvo dos mensajes de Fortify/Jetstream que la web también muestra en inglés, ver `PUT /perfil/contrasena` y `DELETE /cuenta`). |

### Forma de las respuestas

- Éxito: `{ "data": ..., "message"?: "..." }`.
- Listas paginadas: `{ "data": [...], "links": {...}, "meta": { "current_page", "last_page", "per_page", "total", "from", "to", "path", "links" } }`.
- Error de validación o de regla de negocio: **422** `{ "message": "...", "errors": { "campo": ["..."] } }`.
  Las reglas de negocio (ventana cerrada, onboarding pendiente, modo dual...) usan el mismo formato; el campo suele ser
  `error`, `role` u `onboarding`.
- **401** sin token o token inválido/caducado: `{ "message": "Unauthenticated." }` → la app debe volver al login.
- **403** sin permiso: `{ "message": "This action is unauthorized." }` (o un mensaje propio).
- **404** recurso inexistente: `{ "message": "..." }`.
- **429** demasiadas peticiones: `{ "message": "Too Many Attempts." }` + cabecera `Retry-After` (segundos).

> En local (`APP_DEBUG=true`) los 403/404/500 traen además `exception`, `file`, `line` y `trace`. En producción solo
> llega `message`. La app **solo** debe leer `message` y `errors`.

### Onboarding

`/auth/login`, `/auth/me` y la mayoría de acciones de onboarding devuelven:

```json
"onboarding": {
  "aviso_aceptado": true,
  "rol_seleccionado": true,
  "perfil_completo": false,
  "siguiente_paso": "completar_perfil",
  "rol_activo": "comprador"
}
```

`siguiente_paso` ∈ `aceptar_aviso` → `seleccionar_rol` → `completar_perfil` → `ninguno`, en ese orden (el mismo que
imponen los middlewares de la web). La app debe llevar al usuario a la pantalla correspondiente mientras no sea
`ninguno`. `rol_activo` ∈ `comprador | proveedor | admin`.

- `completar_perfil` para un **comprador** (o cuenta con los dos roles) se resuelve con `POST /perfil/completar`.
- Para un **proveedor puro** (`es_proveedor: true`, `es_comprador: false`) depende del perfil de NEGOCIO
  (`perfil_proveedor_completo`), que se completa con el wizard de la sección 12 (`GET /proveedor/perfil` →
  `PUT /proveedor/perfil/{seccion}`, `POST /proveedor/logo`, `POST /proveedor/productos`).

### Límites de peticiones

| Limitador | Rutas | Límite |
|---|---|---|
| `api-login` | `POST /auth/login` | 5/min por correo+IP y 20/min por IP |
| `api-cuenta` | `POST /auth/registro`, `/auth/reenviar-verificacion`, `/auth/olvide-contrasena` | 3/min por correo+IP y 10/min por IP |
| `api-v1` | todo lo autenticado | 120/min por usuario |
| `api-documentos` | `POST /perfil/documentos` | 10/min por usuario |
| `api-credenciales` | `PUT /perfil/contrasena`, `DELETE /cuenta` | 6/min por usuario |
| `api-registro-evento` | `POST /eventos/{id}/registro` | 10/min por usuario |
| `api-archivos` | `GET /documentos/{user}/{tipo}` (URL firmada) | 60/min por IP |
| `api-agendar` | `POST /citas` | 10/min por usuario |
| `api-respuestas` | `POST /citas/{id}/reagenda/*`, `POST /agendas/{id}/aceptar\|rechazar` | 10/min por usuario |
| `api-encuestas` | `POST /encuestas/{id}/respuestas` | 20/min por usuario |
| `api-proveedor-perfil` | `PUT /proveedor/perfil*`, `POST /proveedor/logo`, altas/ediciones/bajas de `/proveedor/productos` | 20/min por usuario |
| `api-proveedor-citas` | `POST /proveedor/citas/{id}/aceptar\|rechazar\|reagendar` | 30/min por usuario |

### Objeto `user` (UserResource)

Aparece en login, `me`, onboarding, perfil/roles e inicio. **Nunca** incluye rutas de documentos, contraseña,
`remember_token` ni `two_factor_*`.

| Campo | Tipo | Nota |
|---|---|---|
| `id`, `name`, `email` | | |
| `email_verificado` | bool | |
| `telefono`, `municipio`, `nombre_empresa` | string\|null | |
| `rol_activo` | string\|null | `comprador`, `proveedor` o `admin` |
| `roles` | string[] | nombres internos: `cliente` (= comprador), `restaurantero` (= proveedor), `admin`, `super-admin` |
| `perfil_completo` | bool | el mismo criterio que `onboarding.perfil_completo` |
| `es_comprador`, `es_proveedor`, `tiene_dual_rol` | bool | |
| `perfil_proveedor_completo` | bool\|null | perfil de NEGOCIO; `null` si no es proveedor |
| `tiene_ine`, `tiene_csf` | bool | |
| `csf_fecha` | string\|null | |
| `foto_url` | string\|null | siempre `null` hoy (la web no tiene foto de perfil) |

---

## 1. Autenticación y cuenta (sin token)

### POST /auth/login

Body JSON: `email` (req), `password` (req), `device_name` (req, máx. 100, p. ej. "iPhone de Ana").

200:
`HTTP 200`
```json
{
  "data": {
    "token": "8|0TmCt2oSCeHPO6oHzLjsC53NAiHLzK6sQUdwgOvWaf772f5b",
    "user": {
      "id": 14,
      "name": "Comprador de Prueba",
      "email": "comprador@prueba.impulsate.mx",
      "email_verificado": true,
      "telefono": null,
      "municipio": "Mérida",
      "nombre_empresa": "Empresa de Prueba",
      "rol_activo": "comprador",
      "roles": [
        "cliente"
      ],
      "perfil_completo": true,
      "es_comprador": true,
      "es_proveedor": false,
      "tiene_dual_rol": false,
      "perfil_proveedor_completo": null,
      "tiene_ine": false,
      "tiene_csf": false,
      "csf_fecha": null,
      "foto_url": null
    },
    "onboarding": {
      "aviso_aceptado": true,
      "rol_seleccionado": true,
      "perfil_completo": true,
      "siguiente_paso": "ninguno",
      "rol_activo": "comprador"
    }
  }
}
```

422 credenciales:
`HTTP 422`
```json
{
  "message": "Las credenciales no son correctas.",
  "errors": {
    "email": [
      "Las credenciales no son correctas."
    ]
  }
}
```

403 cuenta sin verificar: `{ "message": "Verifica tu correo antes de entrar. Revisa tu bandeja de entrada.", "errors": { "email": ["..."] } }`
(sin token). 429 al 6.º intento en un minuto.

### POST /auth/registro

Alta de comprador, la misma que el formulario web `/register` (acción Fortify `CreateNewUser`). **No devuelve token**:
tras el 201 la app hace `POST /auth/login`. La web **no** distingue comprador/proveedor en el registro: todas las
cuentas nacen como comprador (`cliente`) y el rol se elige después en `POST /onboarding/rol`.

Body JSON:

| Campo | Reglas |
|---|---|
| `name` | requerido, máx. 255 |
| `email` | requerido, email, máx. 255, no registrado |
| `telefono` | opcional, máx. 20 |
| `curp` | opcional, exactamente 18 caracteres |
| `password` | requerido, mínimo 8, con `password_confirmation` |
| `acepta_aviso` | requerido, `true` (queda registrado como aceptación del Aviso de Privacidad) |

201:
`HTTP 201`
```json
{
  "data": {
    "email": "docs.app@prueba.impulsate.mx",
    "verificacion_pendiente": false
  },
  "message": "Cuenta creada. Ya puedes iniciar sesión con tu correo y contraseña."
}
```

`verificacion_pendiente` es `false` hoy porque la web marca el correo como verificado al registrarse (decisión
tomada por las caídas de SMTP de agosto). Si algún día se reactiva la verificación, llegará `true` con el mensaje
"Cuenta creada. Revisa tu correo para verificarla antes de iniciar sesión." y el login responderá 403 hasta verificar.

422 correo ya registrado:
`HTTP 422`
```json
{
  "message": "Ya existe una cuenta con este correo. Inicia sesión o recupera tu contraseña.",
  "errors": {
    "email": [
      "Ya existe una cuenta con este correo. Inicia sesión o recupera tu contraseña."
    ]
  }
}
```

422 campos:
`HTTP 422`
```json
{
  "message": "El campo nombre es obligatorio. (and 3 more errors)",
  "errors": {
    "name": [
      "El campo nombre es obligatorio."
    ],
    "password": [
      "El campo contraseña debe tener al menos 8 caracteres.",
      "La confirmación de contraseña no coincide."
    ],
    "acepta_aviso": [
      "Debes aceptar el Aviso de Privacidad para registrarte."
    ]
  }
}
```

### POST /auth/reenviar-verificacion

Body: `email`. Respuesta **idéntica** exista o no el correo (no revela cuentas). Solo envía si la cuenta existe y no
está verificada.

200:
`HTTP 200`
```json
{
  "data": null,
  "message": "Si el correo está registrado y aún no está verificado, te enviamos un nuevo enlace de verificación."
}
```

422 si `email` falta o no es un correo.

### POST /auth/olvide-contrasena

Body: `email`. Envía el mismo correo de restablecimiento que la web (`/forgot-password`); el enlace abre
`/reset-password/{token}` **en la web**. Respuesta genérica.

200:
`HTTP 200`
```json
{
  "data": null,
  "message": "Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña. Revisa tu bandeja de entrada."
}
```

422 si `email` falta o no es un correo.

---

## 2. Sesión (con token)

### GET /auth/me

200 (comprador):
`HTTP 200`
```json
{
  "data": {
    "user": {
      "id": 14,
      "name": "Comprador de Prueba",
      "email": "comprador@prueba.impulsate.mx",
      "email_verificado": true,
      "telefono": null,
      "municipio": "Mérida",
      "nombre_empresa": "Empresa de Prueba",
      "rol_activo": "comprador",
      "roles": [
        "cliente"
      ],
      "perfil_completo": true,
      "es_comprador": true,
      "es_proveedor": false,
      "tiene_dual_rol": false,
      "perfil_proveedor_completo": null,
      "tiene_ine": false,
      "tiene_csf": false,
      "csf_fecha": null,
      "foto_url": null
    },
    "onboarding": {
      "aviso_aceptado": true,
      "rol_seleccionado": true,
      "perfil_completo": true,
      "siguiente_paso": "ninguno",
      "rol_activo": "comprador"
    }
  }
}
```

200 (proveedor con perfil de negocio completo):
`HTTP 200`
```json
{
  "data": {
    "user": {
      "id": 23,
      "name": "Proveedor de Prueba",
      "email": "proveedor@prueba.impulsate.mx",
      "email_verificado": true,
      "telefono": "9991112233",
      "municipio": "Mérida",
      "nombre_empresa": null,
      "rol_activo": "proveedor",
      "roles": [
        "restaurantero"
      ],
      "perfil_completo": true,
      "es_comprador": false,
      "es_proveedor": true,
      "tiene_dual_rol": false,
      "perfil_proveedor_completo": true,
      "tiene_ine": false,
      "tiene_csf": false,
      "csf_fecha": null,
      "foto_url": null
    },
    "onboarding": {
      "aviso_aceptado": true,
      "rol_seleccionado": true,
      "perfil_completo": true,
      "siguiente_paso": "ninguno",
      "rol_activo": "proveedor"
    }
  }
}
```

200 (recién registrado, falta elegir rol):
`HTTP 200`
```json
{
  "data": {
    "user": {
      "id": 26,
      "name": "Usuario Docs",
      "email": "docs.app@prueba.impulsate.mx",
      "email_verificado": true,
      "telefono": "9990000000",
      "municipio": null,
      "nombre_empresa": null,
      "rol_activo": "comprador",
      "roles": [
        "cliente"
      ],
      "perfil_completo": false,
      "es_comprador": true,
      "es_proveedor": false,
      "tiene_dual_rol": false,
      "perfil_proveedor_completo": null,
      "tiene_ine": false,
      "tiene_csf": false,
      "csf_fecha": null,
      "foto_url": null
    },
    "onboarding": {
      "aviso_aceptado": true,
      "rol_seleccionado": false,
      "perfil_completo": false,
      "siguiente_paso": "seleccionar_rol",
      "rol_activo": "comprador"
    }
  }
}
```

### POST /auth/logout

Revoca solo el token actual.
`HTTP 200`
```json
{
  "data": null,
  "message": "Sesión cerrada."
}
```

### POST /auth/logout-todos

Revoca todos los tokens del usuario.
`HTTP 200`
```json
{
  "data": null,
  "message": "Sesión cerrada en todos los dispositivos."
}
```

---

## 3. Onboarding

### GET /aviso-privacidad

El texto del aviso vive en la página web; la app lo abre en un navegador/WebView con `url`.

200:
`HTTP 200`
```json
{
  "data": {
    "titulo": "Aviso de Privacidad",
    "url": "http://localhost/citas/public/aviso-de-privacidad",
    "terminos_url": "http://localhost/citas/public/terminos-y-condiciones",
    "aceptado": true,
    "aceptado_at": "2026-09-22T13:38:10-06:00"
  }
}
```

### POST /onboarding/aceptar-aviso

Body: `acepta: true`.

200:
`HTTP 200`
```json
{
  "data": {
    "user": {
      "id": 26,
      "name": "Usuario Docs",
      "email": "docs.app@prueba.impulsate.mx",
      "email_verificado": true,
      "telefono": "9990000000",
      "municipio": null,
      "nombre_empresa": null,
      "rol_activo": "comprador",
      "roles": [
        "cliente"
      ],
      "perfil_completo": false,
      "es_comprador": true,
      "es_proveedor": false,
      "tiene_dual_rol": false,
      "perfil_proveedor_completo": null,
      "tiene_ine": false,
      "tiene_csf": false,
      "csf_fecha": null,
      "foto_url": null
    },
    "onboarding": {
      "aviso_aceptado": true,
      "rol_seleccionado": false,
      "perfil_completo": false,
      "siguiente_paso": "seleccionar_rol",
      "rol_activo": "comprador"
    }
  },
  "message": "Aviso de Privacidad aceptado."
}
```

422:
`HTTP 422`
```json
{
  "message": "Debes aceptar el Aviso de Privacidad para continuar.",
  "errors": {
    "acepta": [
      "Debes aceptar el Aviso de Privacidad para continuar."
    ]
  }
}
```

### POST /onboarding/rol

Body: `rol` ∈ `comprador | proveedor | ambos` (los mismos tres de la pantalla web `/seleccionar-rol`).

- `comprador`: rol `cliente`, `rol_activo = comprador`.
- `proveedor`: rol `restaurantero`, `rol_activo = proveedor` y crea el perfil de negocio vacío (servicio "Mesa de
  Networking" de 30 min y horario L–V 9:00–16:00). No inscribe a ningún evento.
- `ambos`: los dos roles, `rol_activo = comprador`, y crea el perfil de negocio.

Requiere haber aceptado el aviso (si no: 422 `errors.onboarding`).

200:
`HTTP 200`
```json
{
  "data": {
    "user": {
      "id": 26,
      "name": "Usuario Docs",
      "email": "docs.app@prueba.impulsate.mx",
      "email_verificado": true,
      "telefono": "9990000000",
      "municipio": null,
      "nombre_empresa": null,
      "rol_activo": "comprador",
      "roles": [
        "cliente"
      ],
      "perfil_completo": false,
      "es_comprador": true,
      "es_proveedor": false,
      "tiene_dual_rol": false,
      "perfil_proveedor_completo": null,
      "tiene_ine": false,
      "tiene_csf": false,
      "csf_fecha": null,
      "foto_url": null
    },
    "onboarding": {
      "aviso_aceptado": true,
      "rol_seleccionado": true,
      "perfil_completo": false,
      "siguiente_paso": "completar_perfil",
      "rol_activo": "comprador"
    }
  },
  "message": "¡Bienvenido a Impulsate!"
}
```

422:
`HTTP 422`
```json
{
  "message": "El campo rol seleccionado no es válido.",
  "errors": {
    "rol": [
      "El campo rol seleccionado no es válido."
    ]
  }
}
```

---

## 4. Perfil del comprador

### GET /perfil

Campos del formulario "Datos personales / Mi empresa / Necesidades" de la web y resumen de documentos.

200:
`HTTP 200`
```json
{
  "data": {
    "name": "Usuario Docs",
    "email": "docs.app@prueba.impulsate.mx",
    "telefono": "9990000000",
    "curp": null,
    "rfc": null,
    "genero": null,
    "municipio": null,
    "nombre_empresa": null,
    "sitio_web": null,
    "necesidades": null,
    "camara_asociacion": null,
    "nombre_establecimiento": null,
    "es_restaurantero": false,
    "perfil_completo": false,
    "documentos": {
      "ine": {
        "subido": false,
        "url_temporal": null
      },
      "csf": {
        "subido": false,
        "fecha": null,
        "vigente": false,
        "url_temporal": null
      },
      "url_expira_en_minutos": 10
    }
  }
}
```

### PUT /perfil

Equivale a guardar el perfil en la web (`/mi-panel/perfil`). **Se envía el formulario completo**: los campos opcionales
que no se manden quedan en `null`, igual que en la web. No exige onboarding previo.

| Campo | Reglas |
|---|---|
| `name` | requerido, máx. 255 |
| `telefono` | opcional, máx. 20 |
| `curp` | opcional, máx. 18 (se guarda en mayúsculas) |
| `rfc` | opcional, máx. 13 (se guarda en mayúsculas) |
| `municipio` | opcional, máx. 100 (lista en `/catalogos`) |
| `genero` | opcional: `hombre`, `mujer` o vacío/`null` ("Prefiero no decir") |
| `nombre_empresa` | requerido, máx. 200 |
| `sitio_web` | opcional, URL, máx. 200 |
| `necesidades` | opcional, máx. 2000 |
| `camara_asociacion` | requerido: `CANIRAC` o `Ninguna` |
| `nombre_establecimiento` | requerido si `camara_asociacion = CANIRAC` (si no, se guarda `null`) |
| `es_restaurantero` | bool. Al pasar de `false` a `true` se crea una solicitud **pendiente** como comprador en el encuentro de negocios principal y se notifica a los admins. |

200:
`HTTP 200`
```json
{
  "data": {
    "name": "Usuario Docs",
    "email": "docs.app@prueba.impulsate.mx",
    "telefono": "9990000000",
    "curp": "DOCA800101HYNRRR09",
    "rfc": "DOCA800101AB1",
    "genero": "mujer",
    "municipio": "Mérida",
    "nombre_empresa": "Cocina Docs",
    "sitio_web": "https://cocina.example.com",
    "necesidades": "Miel y café de la región",
    "camara_asociacion": "CANIRAC",
    "nombre_establecimiento": "Fonda Docs",
    "es_restaurantero": false,
    "perfil_completo": true,
    "documentos": {
      "ine": {
        "subido": false,
        "url_temporal": null
      },
      "csf": {
        "subido": false,
        "fecha": null,
        "vigente": false,
        "url_temporal": null
      },
      "url_expira_en_minutos": 10
    }
  },
  "message": "Perfil actualizado correctamente."
}
```

422:
`HTTP 422`
```json
{
  "message": "El campo nombre es obligatorio. (and 1 more error)",
  "errors": {
    "name": [
      "El campo nombre es obligatorio."
    ],
    "sitio_web": [
      "El campo sitio web debe ser una URL válida."
    ]
  }
}
```

### POST /perfil/completar

El formulario de `/completar-perfil` de la web; deja `perfil_completo = true`. Requiere aviso aceptado y rol elegido
(si no: 422 `errors.onboarding`).

| Campo | Reglas |
|---|---|
| `telefono` | requerido, máx. 20 |
| `curp` | opcional, máx. 18 |
| `rfc` | opcional, máx. 13 |
| `municipio` | requerido, máx. 100 |
| `nombre_empresa` | requerido, máx. 200 |
| `camara_asociacion` | requerido: `CANIRAC` o `Ninguna` |
| `nombre_establecimiento` | requerido si `CANIRAC` |
| `necesidades` | requerido, máx. 2000 |
| `es_restaurantero` | bool. Si `true`: solicitud **pendiente** al encuentro de negocios principal (si aún no tenía registro). |

200:
`HTTP 200`
```json
{
  "data": {
    "user": {
      "id": 26,
      "name": "Usuario Docs",
      "email": "docs.app@prueba.impulsate.mx",
      "email_verificado": true,
      "telefono": "9990000000",
      "municipio": "Mérida",
      "nombre_empresa": "Cocina Docs",
      "rol_activo": "comprador",
      "roles": [
        "cliente"
      ],
      "perfil_completo": true,
      "es_comprador": true,
      "es_proveedor": false,
      "tiene_dual_rol": false,
      "perfil_proveedor_completo": null,
      "tiene_ine": false,
      "tiene_csf": false,
      "csf_fecha": null,
      "foto_url": null
    },
    "onboarding": {
      "aviso_aceptado": true,
      "rol_seleccionado": true,
      "perfil_completo": true,
      "siguiente_paso": "ninguno",
      "rol_activo": "comprador"
    }
  },
  "message": "¡Perfil completado! Ya puedes acceder a todas las funciones."
}
```

422:
`HTTP 422`
```json
{
  "message": "El campo telefono es obligatorio. (and 4 more errors)",
  "errors": {
    "telefono": [
      "El campo telefono es obligatorio."
    ],
    "municipio": [
      "El campo municipio es obligatorio."
    ],
    "nombre_empresa": [
      "El campo nombre empresa es obligatorio."
    ],
    "nombre_establecimiento": [
      "El campo nombre establecimiento es obligatorio."
    ],
    "necesidades": [
      "El campo necesidades es obligatorio."
    ]
  }
}
```

### GET /perfil/documentos

`url_temporal` es una URL **firmada** que caduca en `url_expira_en_minutos` (10). Se abre sin cabecera
`Authorization` (sirve para `<Image>`, WebView o `Linking.openURL`). Responde el archivo con
`Content-Disposition: inline`. Si la URL se altera o caduca → 403 `{ "message": "Invalid signature." }`; si el
archivo ya no existe → 404. La firma incluye al solicitante: si deja de ser el dueño o admin, 403.
`csf.vigente` = la CSF tiene fecha y no tiene más de 3 meses (misma regla que el registro al bazar).

200 (sin documentos):
`HTTP 200`
```json
{
  "data": {
    "ine": {
      "subido": false,
      "url_temporal": null
    },
    "csf": {
      "subido": false,
      "fecha": null,
      "vigente": false,
      "url_temporal": null
    },
    "url_expira_en_minutos": 10
  }
}
```

200 (con documentos):
`HTTP 200`
```json
{
  "data": {
    "ine": {
      "subido": true,
      "url_temporal": "http://localhost/citas/public/api/v1/documentos/26/ine?expires=1790106505&solicitante=26&signature=50593daf362de33675307099d750337124bdae0dbf55506d7590f4f713619c6c"
    },
    "csf": {
      "subido": true,
      "fecha": "2026-09-01",
      "vigente": true,
      "url_temporal": "http://localhost/citas/public/api/v1/documentos/26/csf?expires=1790106505&solicitante=26&signature=bc200571e977444ae750cf8b587144e2e988f3518a4393dc487d189ce1f37004"
    },
    "url_expira_en_minutos": 10
  }
}
```

### POST /perfil/documentos

`multipart/form-data`. Mismas reglas que la web:

| Campo | Reglas |
|---|---|
| `ine` | opcional, archivo `pdf`, `jpg`, `jpeg` o `png`, máx. 5 MB |
| `csf` | opcional, archivo `pdf`, `jpg`, `jpeg` o `png`, máx. 5 MB |
| `csf_fecha` | opcional, fecha `YYYY-MM-DD`, no futura |

**HEIC no se acepta** (la web tampoco): la app debe convertir la foto a JPEG antes de subirla. Cada archivo nuevo
reemplaza al anterior. Se guardan en disco privado. Requiere aviso aceptado y rol elegido.

200:
`HTTP 200`
```json
{
  "data": {
    "ine": {
      "subido": true,
      "url_temporal": "http://localhost/citas/public/api/v1/documentos/26/ine?expires=1790106505&solicitante=26&signature=50593daf362de33675307099d750337124bdae0dbf55506d7590f4f713619c6c"
    },
    "csf": {
      "subido": true,
      "fecha": "2026-09-01",
      "vigente": true,
      "url_temporal": "http://localhost/citas/public/api/v1/documentos/26/csf?expires=1790106505&solicitante=26&signature=bc200571e977444ae750cf8b587144e2e988f3518a4393dc487d189ce1f37004"
    },
    "url_expira_en_minutos": 10
  },
  "message": "Documentos actualizados correctamente."
}
```

422:
`HTTP 422`
```json
{
  "message": "El campo ine debe ser un archivo con extensión: pdf, jpg, jpeg, png. (and 1 more error)",
  "errors": {
    "ine": [
      "El campo ine debe ser un archivo con extensión: pdf, jpg, jpeg, png."
    ],
    "csf_fecha": [
      "La fecha de la CSF no puede ser futura."
    ]
  }
}
```

Ejemplo de mensajes de peso: `"La CSF no debe pesar más de 5 MB."`, `"El INE no debe pesar más de 5 MB."`.

### GET /documentos/{user}/{tipo}

La ruta a la que apunta `url_temporal` (no se construye a mano). 200 con el archivo (`image/png`, `image/jpeg` o
`application/pdf`); 403 firma inválida/caducada o sin permiso; 404 no subido.

### POST /perfil/cambiar-rol

Alterna `rol_activo` entre comprador y proveedor para cuentas con los dos roles (el switch de la web). Sin body.
Requiere onboarding completo (si no: 422 `errors.onboarding` o `errors.error`).

200:
`HTTP 200`
```json
{
  "data": {
    "user": {
      "id": 26,
      "name": "Usuario Docs",
      "email": "docs.app@prueba.impulsate.mx",
      "email_verificado": true,
      "telefono": "9990000000",
      "municipio": "Mérida",
      "nombre_empresa": "Cocina Docs",
      "rol_activo": "proveedor",
      "roles": [
        "restaurantero",
        "cliente"
      ],
      "perfil_completo": true,
      "es_comprador": true,
      "es_proveedor": true,
      "tiene_dual_rol": true,
      "perfil_proveedor_completo": false,
      "tiene_ine": true,
      "tiene_csf": true,
      "csf_fecha": "2026-09-01",
      "foto_url": null
    },
    "onboarding": {
      "aviso_aceptado": true,
      "rol_seleccionado": true,
      "perfil_completo": true,
      "siguiente_paso": "ninguno",
      "rol_activo": "proveedor"
    }
  },
  "message": "Ahora estás en modo proveedor."
}
```

422 sin doble rol:
`HTTP 422`
```json
{
  "message": "No tienes habilitado el modo dual.",
  "errors": {
    "role": [
      "No tienes habilitado el modo dual."
    ]
  }
}
```

422 con una cita pendiente/confirmada en la próxima hora:
`{ "message": "Tienes una cita en 25 minutos. No puedes cambiar de perfil ahora.", "errors": { "role": ["..."] } }`
(o "Tienes una cita como proveedor en N minutos...").

### POST /perfil/agregar-rol

Body: `rol` ∈ `comprador | proveedor`. Agrega el rol sin cambiar el activo. `proveedor` crea el perfil de negocio
vacío si no existía. No exige onboarding previo.

200:
`HTTP 200`
```json
{
  "data": {
    "user": {
      "id": 26,
      "name": "Usuario Docs",
      "email": "docs.app@prueba.impulsate.mx",
      "email_verificado": true,
      "telefono": "9990000000",
      "municipio": "Mérida",
      "nombre_empresa": "Cocina Docs",
      "rol_activo": "comprador",
      "roles": [
        "restaurantero",
        "cliente"
      ],
      "perfil_completo": true,
      "es_comprador": true,
      "es_proveedor": true,
      "tiene_dual_rol": true,
      "perfil_proveedor_completo": false,
      "tiene_ine": true,
      "tiene_csf": true,
      "csf_fecha": "2026-09-01",
      "foto_url": null
    },
    "onboarding": {
      "aviso_aceptado": true,
      "rol_seleccionado": true,
      "perfil_completo": true,
      "siguiente_paso": "ninguno",
      "rol_activo": "comprador"
    }
  },
  "message": "Rol agregado correctamente. ¡Bienvenido!"
}
```

422: `{ "message": "El campo rol seleccionado no es válido.", "errors": { "rol": ["..."] } }`

### PUT /perfil/contrasena

Body: `current_password`, `password` (mín. 8), `password_confirmation`. Misma acción que la web
(`UpdateUserPassword`). No revoca los tokens existentes (la web tampoco cierra otras sesiones).

200:
`HTTP 200`
```json
{
  "data": null,
  "message": "Contraseña actualizada."
}
```

422 (el mensaje viene de Fortify y está en inglés también en la web):
`HTTP 422`
```json
{
  "message": "The provided password does not match your current password.",
  "errors": {
    "current_password": [
      "The provided password does not match your current password."
    ]
  }
}
```

### DELETE /cuenta

Body JSON: `password`. Borra la cuenta como la web (Jetstream `DeleteUser`): tokens, perfil de negocio, citas,
registros a eventos y notificaciones. Irreversible.

200:
`HTTP 200`
```json
{
  "data": null,
  "message": "Tu cuenta fue eliminada."
}
```

422:
`HTTP 422`
```json
{
  "message": "This password does not match our records.",
  "errors": {
    "password": [
      "This password does not match our records."
    ]
  }
}
```

---

## 5. Notificaciones

Solo las propias: leer o borrar una ajena → 403. Las rutas `/api/notificaciones` (sin `v1`) son del polling de la web
y no cambian. Al listar o contar se generan, como en la web, los recordatorios "cita en ~30 min" para admins y
proveedores.

`tipo` ∈ `solicitud_registro`, `solicitud_aprobada`, `solicitud_rechazada`, `cita_nueva`, `cita_aceptada`,
`cita_rechazada`, `cita_reagendada`, `cita_cancelada`, `recordatorio_24h`, `recordatorio_2h`, `recordatorio_1h`,
`recordatorio_30m`, `info`. `cita_id` puede ser `null`.

### GET /notificaciones

Query: `no_leidas=1` (solo no leídas), `per_page` (1–50, por defecto 20), `page`. Orden: más recientes primero.
`meta.no_leidas` = total de no leídas del usuario (independiente del filtro).

200 (`?per_page=3`):
`HTTP 200`
```json
{
  "data": [
    {
      "id": 22,
      "tipo": "info",
      "titulo": "Recuerda llevar tu INE",
      "mensaje": "El día del evento presenta tu INE en el registro.",
      "leida": false,
      "cita_id": null,
      "created_at": "2026-09-22T13:38:08-06:00"
    },
    {
      "id": 16,
      "tipo": "cita_cancelada",
      "titulo": "Cita cancelada",
      "mensaje": "Tu cita con Café Puuc fue cancelada.",
      "leida": true,
      "cita_id": null,
      "created_at": "2026-09-22T13:35:33-06:00"
    },
    {
      "id": 15,
      "tipo": "cita_nueva",
      "titulo": "Nueva cita asignada",
      "mensaje": "Se te asignó una cita con Miel Melipona del Mayab.",
      "leida": false,
      "cita_id": null,
      "created_at": "2026-09-22T13:35:33-06:00"
    }
  ],
  "links": {
    "first": "http://localhost/citas/public/api/v1/notificaciones?per_page=3&page=1",
    "last": "http://localhost/citas/public/api/v1/notificaciones?per_page=3&page=2",
    "prev": null,
    "next": "http://localhost/citas/public/api/v1/notificaciones?per_page=3&page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 2,
    "links": [
      {
        "url": null,
        "label": "&laquo; Anterior",
        "page": null,
        "active": false
      },
      {
        "url": "http://localhost/citas/public/api/v1/notificaciones?per_page=3&page=1",
        "label": "1",
        "page": 1,
        "active": true
      },
      {
        "url": "http://localhost/citas/public/api/v1/notificaciones?per_page=3&page=2",
        "label": "2",
        "page": 2,
        "active": false
      },
      {
        "url": "http://localhost/citas/public/api/v1/notificaciones?per_page=3&page=2",
        "label": "Siguiente &raquo;",
        "page": 2,
        "active": false
      }
    ],
    "path": "http://localhost/citas/public/api/v1/notificaciones",
    "per_page": 3,
    "to": 3,
    "total": 5,
    "no_leidas": 3
  }
}
```

### GET /notificaciones/contador

Para el badge / polling.
`HTTP 200`
```json
{
  "data": {
    "no_leidas": 3
  }
}
```

### PATCH /notificaciones/{id}/leer

200:
`HTTP 200`
```json
{
  "data": {
    "id": 22,
    "tipo": "info",
    "titulo": "Recuerda llevar tu INE",
    "mensaje": "El día del evento presenta tu INE en el registro.",
    "leida": true,
    "cita_id": null,
    "created_at": "2026-09-22T13:38:08-06:00"
  }
}
```

403 (ajena, cuerpo en producción): `{ "message": "This action is unauthorized." }`. 404 si no existe.

### POST /notificaciones/leer-todas

`HTTP 200`
```json
{
  "data": {
    "actualizadas": 2
  },
  "message": "Notificaciones marcadas como leídas."
}
```

### DELETE /notificaciones/{id}

`HTTP 200`
```json
{
  "data": null,
  "message": "Notificación eliminada."
}
```

### DELETE /notificaciones

Borra todas las propias.
`HTTP 200`
```json
{
  "data": {
    "eliminadas": 1
  },
  "message": "Notificaciones eliminadas."
}
```

---

## 6. Inicio y catálogos

### GET /inicio

Resumen para la pantalla de Inicio.

- `vista`: `proveedor` si el usuario está en modo proveedor; si no, `comprador`.
- `proxima_cita`: la siguiente cita futura en estado `pendiente`, `confirmada`, `reagendada` o
  `pendiente_reconfirmacion` (en modo comprador: las que agendó; en modo proveedor: las que recibe). En modo
  proveedor incluye además `comprador: {id, nombre, nombre_empresa}`. `null` si no hay.
- `citas_proximas_count`: cuántas citas futuras hay en esos estados.
- `evento_destacado`: el evento principal (`Evento::activo()`: el marcado como destacado, si no el más próximo),
  con la misma forma que `GET /eventos/{id}`; `null` si no hay eventos activos.
- `notificaciones_sin_leer`: entero.
- `publicidad`: el popup vigente que muestra la web (nunca a admins); `null` si no hay.
- `pendientes` (Fase 2): lo que el usuario tiene por responder **como comprador**, para badges/avisos:
  `reagendas_por_confirmar` (citas en `pendiente_reconfirmacion`, ver `GET /citas?estado=pendiente_reconfirmacion`),
  `agendas_por_responder` (`GET /agendas`) y `encuestas_por_responder` (`GET /encuestas/pendientes`). Los demás campos
  no cambiaron.
- `proveedor` (Fase 3): pendientes del lado proveedor (`null` si la cuenta no es proveedor). Ver
  [Cambios en GET /inicio](#cambios-en-get-inicio) en la sección 12.

  Ejemplo real con los datos de prueba (resto de `data` igual que abajo):
  `HTTP 200`
  ```json
  {
    "data": {
      "…": "…",
      "pendientes": {
        "reagendas_por_confirmar": 1,
        "agendas_por_responder": 1,
        "encuestas_por_responder": 1
      }
    }
  }
  ```

200 (comprador, con una publicidad vigente):
`HTTP 200`
```json
{
  "data": {
    "usuario": {
      "id": 14,
      "name": "Comprador de Prueba",
      "email": "comprador@prueba.impulsate.mx",
      "email_verificado": true,
      "telefono": null,
      "municipio": "Mérida",
      "nombre_empresa": "Empresa de Prueba",
      "rol_activo": "comprador",
      "roles": [
        "cliente"
      ],
      "perfil_completo": true,
      "es_comprador": true,
      "es_proveedor": false,
      "tiene_dual_rol": false,
      "perfil_proveedor_completo": null,
      "tiene_ine": false,
      "tiene_csf": false,
      "csf_fecha": null,
      "foto_url": null
    },
    "onboarding": {
      "aviso_aceptado": true,
      "rol_seleccionado": true,
      "perfil_completo": true,
      "siguiente_paso": "ninguno",
      "rol_activo": "comprador"
    },
    "vista": "comprador",
    "proxima_cita": {
      "id": 27,
      "inicio": "2026-10-06T10:00:00-06:00",
      "fin": "2026-10-06T10:30:00-06:00",
      "estado": "confirmada",
      "mesa": "Mesa 3",
      "evento_id": 5,
      "proveedor": {
        "id": 13,
        "nombre_negocio": "Salsas Doña Chela",
        "categoria": "Alimentos y Bebidas",
        "logo_url": "http://localhost/citas/public/storage/logos/demo-proveedor-prueba.png"
      }
    },
    "citas_proximas_count": 2,
    "evento_destacado": {
      "id": 5,
      "nombre": "Encuentro de Negocios Impúlsate (demo app)",
      "descripcion": "Encuentro de negocios entre compradores del sector restaurantero y proveedores locales de Yucatán.",
      "sector_economico": "Alimentos y Bebidas",
      "tipo_evento": "encuentro_negocios",
      "imagen_url": null,
      "convocatoria_url": null,
      "fecha_hora_inicio": "2026-10-06T09:00:00-06:00",
      "fecha_hora_fin": "2026-10-06T18:00:00-06:00",
      "estado": "activo",
      "destacado": true,
      "fases": [
        {
          "clave": "registro_proveedores",
          "titulo": "Registro de proveedores",
          "inicio": "2026-09-17T13:38:55-06:00",
          "fin": "2026-10-04T09:00:00-06:00",
          "estado": "en_curso"
        },
        {
          "clave": "registro_compradores",
          "titulo": "Registro de compradores",
          "inicio": "2026-09-17T13:38:55-06:00",
          "fin": "2026-10-05T09:00:00-06:00",
          "estado": "en_curso"
        },
        {
          "clave": "evento",
          "titulo": "Evento",
          "inicio": "2026-10-06T09:00:00-06:00",
          "fin": "2026-10-06T18:00:00-06:00",
          "estado": "pendiente"
        }
      ],
      "mi_registro": {
        "comprador": {
          "estado": "aprobado",
          "motivo_rechazo": null,
          "registrado_at": "2026-09-22T13:38:55-06:00"
        },
        "proveedor": null,
        "expositor": null
      },
      "tipo_registro": "comprador",
      "puede_registrarse": false,
      "motivo_bloqueo": null,
      "accion": "ver_proveedores"
    },
    "notificaciones_sin_leer": 3,
    "publicidad": {
      "id": 1,
      "titulo": "Expo Gastronómica Mérida",
      "imagen_url": "http://localhost/citas/public/storage/publicidad/expo.jpg",
      "enlace": "https://example.com/expo",
      "abre_en_nueva_pestana": true,
      "fecha_fin": "2026-09-29T13:39:37-06:00"
    }
  }
}
```

200 (proveedor):
`HTTP 200`
```json
{
  "data": {
    "usuario": {
      "id": 23,
      "name": "Proveedor de Prueba",
      "email": "proveedor@prueba.impulsate.mx",
      "email_verificado": true,
      "telefono": "9991112233",
      "municipio": "Mérida",
      "nombre_empresa": null,
      "rol_activo": "proveedor",
      "roles": [
        "restaurantero"
      ],
      "perfil_completo": true,
      "es_comprador": false,
      "es_proveedor": true,
      "tiene_dual_rol": false,
      "perfil_proveedor_completo": true,
      "tiene_ine": false,
      "tiene_csf": false,
      "csf_fecha": null,
      "foto_url": null
    },
    "onboarding": {
      "aviso_aceptado": true,
      "rol_seleccionado": true,
      "perfil_completo": true,
      "siguiente_paso": "ninguno",
      "rol_activo": "proveedor"
    },
    "vista": "proveedor",
    "proxima_cita": {
      "id": 27,
      "inicio": "2026-10-06T10:00:00-06:00",
      "fin": "2026-10-06T10:30:00-06:00",
      "estado": "confirmada",
      "mesa": "Mesa 3",
      "evento_id": 5,
      "proveedor": {
        "id": 13,
        "nombre_negocio": "Salsas Doña Chela",
        "categoria": "Alimentos y Bebidas",
        "logo_url": "http://localhost/citas/public/storage/logos/demo-proveedor-prueba.png"
      },
      "comprador": {
        "id": 14,
        "nombre": "Comprador de Prueba",
        "nombre_empresa": "Empresa de Prueba"
      }
    },
    "citas_proximas_count": 2,
    "evento_destacado": {
      "id": 5,
      "nombre": "Encuentro de Negocios Impúlsate (demo app)",
      "descripcion": "Encuentro de negocios entre compradores del sector restaurantero y proveedores locales de Yucatán.",
      "sector_economico": "Alimentos y Bebidas",
      "tipo_evento": "encuentro_negocios",
      "imagen_url": null,
      "convocatoria_url": null,
      "fecha_hora_inicio": "2026-10-06T09:00:00-06:00",
      "fecha_hora_fin": "2026-10-06T18:00:00-06:00",
      "estado": "activo",
      "destacado": true,
      "fases": [
        {
          "clave": "registro_proveedores",
          "titulo": "Registro de proveedores",
          "inicio": "2026-09-17T13:38:08-06:00",
          "fin": "2026-10-04T09:00:00-06:00",
          "estado": "en_curso"
        },
        {
          "clave": "registro_compradores",
          "titulo": "Registro de compradores",
          "inicio": "2026-09-17T13:38:08-06:00",
          "fin": "2026-10-05T09:00:00-06:00",
          "estado": "en_curso"
        },
        {
          "clave": "evento",
          "titulo": "Evento",
          "inicio": "2026-10-06T09:00:00-06:00",
          "fin": "2026-10-06T18:00:00-06:00",
          "estado": "pendiente"
        }
      ],
      "mi_registro": {
        "comprador": null,
        "proveedor": {
          "estado": "aprobado",
          "motivo_rechazo": null,
          "registrado_at": "2026-09-22T13:38:08-06:00"
        },
        "expositor": null
      },
      "tipo_registro": "proveedor",
      "puede_registrarse": false,
      "motivo_bloqueo": null,
      "accion": "ver_proveedores"
    },
    "notificaciones_sin_leer": 0,
    "publicidad": null
  }
}
```

### GET /catalogos

Listas que usan los formularios, con los mismos valores que valida el servidor. `categorias` sale de la tabla que
administra el super-admin (puede cambiar).

200:
`HTTP 200`
```json
{
  "data": {
    "municipios": [
      "Abalá",
      "Acanceh",
      "Akil",
      "Baca",
      "Bokobá",
      "Buctzotz"
    ],
    "generos": [
      {
        "valor": "mujer",
        "etiqueta": "Femenino"
      },
      {
        "valor": "hombre",
        "etiqueta": "Masculino"
      },
      {
        "valor": null,
        "etiqueta": "Prefiero no decir"
      }
    ],
    "camaras_asociacion": [
      "CANIRAC",
      "Ninguna"
    ],
    "categorias": [
      "Alimentos y Bebidas",
      "Tecnología",
      "Salud y Bienestar",
      "Servicios Profesionales",
      "Manufactura",
      "Comercio",
      "Construcción",
      "Educación",
      "Turismo",
      "Logística",
      "Finanzas",
      "Textiles",
      "Artesanías",
      "Muebles y decoración",
      "Servicios impresos",
      "Vinos y licores",
      "Entretenimiento",
      "Blancos",
      "Limpieza e higiene",
      "Equipos, mantenimiento y reparación",
      "Cristalería, plásticos y utensilios",
      "Otro"
    ],
    "roles_seleccionables": [
      {
        "valor": "comprador",
        "etiqueta": "Comprador"
      },
      {
        "valor": "proveedor",
        "etiqueta": "Proveedor"
      },
      {
        "valor": "ambos",
        "etiqueta": "Ambos"
      }
    ],
    "roles_agregables": [
      "comprador",
      "proveedor"
    ],
    "documentos": {
      "tipos": [
        "ine",
        "csf"
      ],
      "formatos": [
        "pdf",
        "jpg",
        "jpeg",
        "png"
      ],
      "max_kb": 5120,
      "csf_vigencia_meses": 3
    }
  }
}
```
_Recortado: `municipios` trae 106 municipios ordenados alfabéticamente; se muestran los 6 primeros._


---

## 7. Eventos (ya existentes)

### GET /eventos

Eventos activos y aquellos en los que el usuario tiene registro, paginados de 20 en 20. Cada evento trae la decisión
ya tomada por el servidor: `tipo_registro` (`comprador | proveedor | expositor | null`), `puede_registrarse`,
`motivo_bloqueo` y `accion`, además de `mi_registro` por tipo y `fases`.

Fase 3: con `?vista=proveedor` (también en `GET /eventos/{id}`) una cuenta con los dos roles ve `tipo_registro`,
`accion` y `motivo_bloqueo` como proveedor aunque esté en modo comprador; `mi_registro.expositor` trae además
`seleccionado` (bool). Ver [Registro del proveedor a eventos](#registro-del-proveedor-a-eventos).

200:
`HTTP 200`
```json
{
  "data": [
    {
      "id": 5,
      "nombre": "Encuentro de Negocios Impúlsate (demo app)",
      "descripcion": "Encuentro de negocios entre compradores del sector restaurantero y proveedores locales de Yucatán.",
      "sector_economico": "Alimentos y Bebidas",
      "tipo_evento": "encuentro_negocios",
      "imagen_url": null,
      "convocatoria_url": null,
      "fecha_hora_inicio": "2026-10-06T09:00:00-06:00",
      "fecha_hora_fin": "2026-10-06T18:00:00-06:00",
      "estado": "activo",
      "destacado": true,
      "fases": [
        {
          "clave": "registro_proveedores",
          "titulo": "Registro de proveedores",
          "inicio": "2026-09-17T13:38:08-06:00",
          "fin": "2026-10-04T09:00:00-06:00",
          "estado": "en_curso"
        },
        {
          "clave": "registro_compradores",
          "titulo": "Registro de compradores",
          "inicio": "2026-09-17T13:38:08-06:00",
          "fin": "2026-10-05T09:00:00-06:00",
          "estado": "en_curso"
        },
        {
          "clave": "evento",
          "titulo": "Evento",
          "inicio": "2026-10-06T09:00:00-06:00",
          "fin": "2026-10-06T18:00:00-06:00",
          "estado": "pendiente"
        }
      ],
      "mi_registro": {
        "comprador": {
          "estado": "aprobado",
          "motivo_rechazo": null,
          "registrado_at": "2026-09-22T13:38:08-06:00"
        },
        "proveedor": null,
        "expositor": null
      },
      "tipo_registro": "comprador",
      "puede_registrarse": false,
      "motivo_bloqueo": null,
      "accion": "ver_proveedores"
    }
  ],
  "links": {
    "first": "http://localhost/citas/public/api/v1/eventos?page=1",
    "last": "http://localhost/citas/public/api/v1/eventos?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "links": [
      {
        "url": null,
        "label": "&laquo; Anterior",
        "page": null,
        "active": false
      },
      {
        "url": "http://localhost/citas/public/api/v1/eventos?page=1",
        "label": "1",
        "page": 1,
        "active": true
      },
      {
        "url": null,
        "label": "Siguiente &raquo;",
        "page": null,
        "active": false
      }
    ],
    "path": "http://localhost/citas/public/api/v1/eventos",
    "per_page": 20,
    "to": 4,
    "total": 4
  }
}
```
_Recortado: `data` traía 4 eventos; se muestra el primero._


### GET /eventos/{id}

403 si el evento está inactivo y el usuario no tiene registro en él.

200:
`HTTP 200`
```json
{
  "data": {
    "id": 5,
    "nombre": "Encuentro de Negocios Impúlsate (demo app)",
    "descripcion": "Encuentro de negocios entre compradores del sector restaurantero y proveedores locales de Yucatán.",
    "sector_economico": "Alimentos y Bebidas",
    "tipo_evento": "encuentro_negocios",
    "imagen_url": null,
    "convocatoria_url": null,
    "fecha_hora_inicio": "2026-10-06T09:00:00-06:00",
    "fecha_hora_fin": "2026-10-06T18:00:00-06:00",
    "estado": "activo",
    "destacado": true,
    "fases": [
      {
        "clave": "registro_proveedores",
        "titulo": "Registro de proveedores",
        "inicio": "2026-09-17T13:38:08-06:00",
        "fin": "2026-10-04T09:00:00-06:00",
        "estado": "en_curso"
      },
      {
        "clave": "registro_compradores",
        "titulo": "Registro de compradores",
        "inicio": "2026-09-17T13:38:08-06:00",
        "fin": "2026-10-05T09:00:00-06:00",
        "estado": "en_curso"
      },
      {
        "clave": "evento",
        "titulo": "Evento",
        "inicio": "2026-10-06T09:00:00-06:00",
        "fin": "2026-10-06T18:00:00-06:00",
        "estado": "pendiente"
      }
    ],
    "mi_registro": {
      "comprador": {
        "estado": "aprobado",
        "motivo_rechazo": null,
        "registrado_at": "2026-09-22T13:38:08-06:00"
      },
      "proveedor": null,
      "expositor": null
    },
    "tipo_registro": "comprador",
    "puede_registrarse": false,
    "motivo_bloqueo": null,
    "accion": "ver_proveedores"
  }
}
```

### POST /eventos/{id}/registro

Body: `tipo` ∈ `comprador | proveedor | expositor`. 201 si se creó o re-postuló la solicitud; 200 si ya existía
(`resultado`: `ya_pendiente`, `ya_aprobado`...). Las reglas (ventanas, perfil completo, INE/CSF para el bazar)
llegan como 422 con `errors.error`.

201:
`HTTP 201`
```json
{
  "data": {
    "id": 5,
    "nombre": "Encuentro de Negocios Impúlsate (demo app)",
    "descripcion": "Encuentro de negocios entre compradores del sector restaurantero y proveedores locales de Yucatán.",
    "sector_economico": "Alimentos y Bebidas",
    "tipo_evento": "encuentro_negocios",
    "imagen_url": null,
    "convocatoria_url": null,
    "fecha_hora_inicio": "2026-10-06T09:00:00-06:00",
    "fecha_hora_fin": "2026-10-06T18:00:00-06:00",
    "estado": "activo",
    "destacado": true,
    "fases": [
      {
        "clave": "registro_proveedores",
        "titulo": "Registro de proveedores",
        "inicio": "2026-09-17T13:38:08-06:00",
        "fin": "2026-10-04T09:00:00-06:00",
        "estado": "en_curso"
      },
      {
        "clave": "registro_compradores",
        "titulo": "Registro de compradores",
        "inicio": "2026-09-17T13:38:08-06:00",
        "fin": "2026-10-05T09:00:00-06:00",
        "estado": "en_curso"
      },
      {
        "clave": "evento",
        "titulo": "Evento",
        "inicio": "2026-10-06T09:00:00-06:00",
        "fin": "2026-10-06T18:00:00-06:00",
        "estado": "pendiente"
      }
    ],
    "mi_registro": {
      "comprador": {
        "estado": "pendiente",
        "motivo_rechazo": null,
        "registrado_at": "2026-09-22T13:38:32-06:00"
      },
      "proveedor": null,
      "expositor": null
    },
    "tipo_registro": "proveedor",
    "puede_registrarse": true,
    "motivo_bloqueo": null,
    "accion": "registrate"
  },
  "message": "Tu solicitud de registro fue enviada. El administrador la revisará pronto.",
  "resultado": "creado"
}
```

422:
`HTTP 422`
```json
{
  "message": "El campo tipo seleccionado no es válido.",
  "errors": {
    "tipo": [
      "El campo tipo seleccionado no es válido."
    ]
  }
}
```

---

## 8. Proveedores (directorio y ficha)

Lo mismo que la web muestra en `/proveedores` y `/proveedores/{id}`. Lo ve cualquier usuario con sesión (la web lo
muestra incluso sin sesión). **Nunca** viajan la dirección del negocio (la ficha web la oculta), el correo, el
motivo de rechazo ni campos internos.

### GET /proveedores

Query (todo opcional):

| Parámetro | Valores | Nota |
|---|---|---|
| `evento_id` | id de un encuentro de negocios **activo** (ver `meta.eventos`) | Proveedores **aprobados** en ese evento (una sección de `/proveedores` en la web). Sin `evento_id`: directorio de la plataforma (perfil activo, aprobado y completo). |
| `search` | texto, máx. 100 | Busca en nombre, descripción, municipio y dirección (igual que la web; la dirección no se devuelve). |
| `categoria` | texto exacto, máx. 100 | Una de `GET /proveedores/categorias`. |
| `orden` | `recientes` (defecto) \| `coincidencia` | `recientes`: más nuevos primero (la web). `coincidencia`: el orden de "Proveedores recomendados" de `/mi-panel` (cuántas palabras de las `necesidades` del perfil aparecen en nombre, descripción, categoría y productos); cada elemento trae `coincidencias`. |
| `per_page` | 1–50 (defecto 20) | |

`meta` añade: `evento` (el aplicado o `null`), `eventos` (encuentros activos para pintar pestañas, el destacado
primero), `orden` y `tiene_necesidades` (si `false`, el orden por coincidencia no cambia nada: invitar a llenar
"Qué buscas" en el perfil).

`citas_count` es el total histórico de citas del proveedor (el mismo contador que muestran las tarjetas web).

200 (`?evento_id=5&per_page=2`):
`HTTP 200`
```json
{
  "data": [
    {
      "id": 13,
      "nombre_negocio": "Salsas Doña Chela",
      "categoria": "Alimentos y Bebidas",
      "municipio": "Mérida",
      "descripcion": "Salsas y recados artesanales yucatecos para restaurantes.",
      "logo_url": "http://localhost/citas/public/storage/logos/demo-proveedor-prueba.png",
      "citas_count": 2
    }
  ],
  "links": {
    "first": "http://127.0.0.1/citas/public/api/v1/proveedores?evento_id=5&per_page=2&page=1",
    "last": "http://127.0.0.1/citas/public/api/v1/proveedores?evento_id=5&per_page=2&page=5",
    "prev": null,
    "next": "http://127.0.0.1/citas/public/api/v1/proveedores?evento_id=5&per_page=2&page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "links": "…",
    "path": "http://127.0.0.1/citas/public/api/v1/proveedores",
    "per_page": 2,
    "to": 2,
    "total": 9,
    "evento": {
      "id": 5,
      "nombre": "Encuentro de Negocios Impúlsate (demo app)",
      "destacado": true,
      "imagen_url": null
    },
    "eventos": [
      {
        "id": 5,
        "nombre": "Encuentro de Negocios Impúlsate (demo app)",
        "destacado": true,
        "imagen_url": null
      },
      {
        "id": 1,
        "nombre": "Edición Inicial",
        "destacado": false,
        "imagen_url": null
      }
    ],
    "orden": "recientes",
    "tiene_necesidades": false
  }
}
```
_Recortado: `data` traía 2 elementos; se muestra el primero; se omite `meta.links` (arreglo de enlaces de página)._

200 (`?evento_id=5&orden=coincidencia&per_page=3`, comprador con necesidades "Salsas, recados y miel para el menú de temporada."):
`HTTP 200`
```json
{
  "data": [
    {
      "id": 13,
      "nombre_negocio": "Salsas Doña Chela",
      "categoria": "Alimentos y Bebidas",
      "municipio": "Mérida",
      "descripcion": "Salsas y recados artesanales yucatecos para restaurantes.",
      "logo_url": "http://localhost/citas/public/storage/logos/demo-proveedor-prueba.png",
      "citas_count": 2,
      "coincidencias": 3
    }
  ],
  "links": {
    "first": "http://127.0.0.1/citas/public/api/v1/proveedores?evento_id=5&orden=coincidencia&per_page=3&page=1",
    "last": "http://127.0.0.1/citas/public/api/v1/proveedores?evento_id=5&orden=coincidencia&per_page=3&page=3",
    "prev": null,
    "next": "http://127.0.0.1/citas/public/api/v1/proveedores?evento_id=5&orden=coincidencia&per_page=3&page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 3,
    "links": "…",
    "path": "http://127.0.0.1/citas/public/api/v1/proveedores",
    "per_page": 3,
    "to": 3,
    "total": 9,
    "evento": {
      "id": 5,
      "nombre": "Encuentro de Negocios Impúlsate (demo app)",
      "destacado": true,
      "imagen_url": null
    },
    "eventos": [
      {
        "id": 5,
        "nombre": "Encuentro de Negocios Impúlsate (demo app)",
        "destacado": true,
        "imagen_url": null
      },
      {
        "id": 1,
        "nombre": "Edición Inicial",
        "destacado": false,
        "imagen_url": null
      }
    ],
    "orden": "coincidencia",
    "tiene_necesidades": true
  }
}
```
_Recortado: `data` traía 3 elementos; se muestra el primero; se omite `meta.links` (arreglo de enlaces de página)._

422 (`evento_id` que no es un encuentro activo):
`HTTP 422`
```json
{
  "message": "El evento no es un encuentro de negocios activo.",
  "errors": {
    "evento_id": [
      "El evento no es un encuentro de negocios activo."
    ]
  }
}
```

### GET /proveedores/categorias

Query: `evento_id` (opcional, mismo alcance que el listado). Devuelve las categorías presentes, ordenadas.

200 (`?evento_id=5`):
`HTTP 200`
```json
{
  "data": [
    "Alimentos y Bebidas",
    "Artesanías",
    "Cristalería, plásticos y utensilios",
    "Equipos, mantenimiento y reparación",
    "Limpieza e higiene",
    "Textiles",
    "Vinos y licores"
  ]
}
```

### GET /proveedores/{id}

Ficha completa como la ve un comprador en la web, agrupada por las secciones de la pantalla. Registra una visita en
las métricas del admin (igual que abrir la ficha web). 404 si el proveedor no existe o está inactivo (salvo que
quien mira sea su dueño o un admin).

- `productos[]`: `nombre`, `descripcion`, `foto_url`, `capacidad_cantidad` + `capacidad_unidad` (capacidad mensual),
  `precio` (puede ser `null`).
- `condiciones_comerciales.credito_a_negociar = true` → mostrar "Crédito a negociar" en lugar de monto/plazo.
- `logistica.entrega_domicilio`: `true`, `false` o `null` (no capturado: no mostrar la sección).
- `empresa` y `producto_servicio`: la web muestra cada campo solo si tiene valor.
- `logo_url`, `foto_url`: URL absoluta o `null`. Se arma con `APP_URL` del servidor: en local sale
  `http://localhost/citas/public/storage/...`, que el teléfono no resuelve; para probar imágenes desde el teléfono,
  poner en `.env` local `APP_URL=http://192.168.10.6/citas/public` (o reemplazar el host en la app en modo desarrollo).

200:
`HTTP 200`
```json
{
  "data": {
    "id": 13,
    "nombre_negocio": "Salsas Doña Chela",
    "categoria": "Alimentos y Bebidas",
    "municipio": "Mérida",
    "descripcion": "Salsas y recados artesanales yucatecos para restaurantes.",
    "logo_url": "http://localhost/citas/public/storage/logos/demo-proveedor-prueba.png",
    "contacto": {
      "telefono": "9991112233",
      "sitio_web": null,
      "rfc": "SDC200101AB1"
    },
    "productos": [
      {
        "nombre": "Recado rojo 1 kg",
        "descripcion": "Achiote artesanal",
        "foto_url": null,
        "capacidad_cantidad": 500,
        "capacidad_unidad": "kilogramos",
        "precio": null
      },
      {
        "nombre": "Salsa de habanero 250 ml",
        "descripcion": "Picor alto",
        "foto_url": null,
        "capacidad_cantidad": 2000,
        "capacidad_unidad": "piezas",
        "precio": null
      }
    ],
    "condiciones_comerciales": {
      "acepta_credito": true,
      "credito_a_negociar": true,
      "credito_monto_maximo": null,
      "credito_tiempo_cantidad": null,
      "credito_tiempo_unidad": null,
      "pago_contraentrega": true,
      "factura": true,
      "regimen_fiscal": "601 - General de Ley Personas Morales"
    },
    "logistica": {
      "entrega_domicilio": true,
      "cobertura_entrega": "regional",
      "forma_entrega": "programada"
    },
    "empresa": {
      "razon_social": "Salsas Doña Chela SA de CV",
      "nombre_representante": "Graciela Pech",
      "curp_representante": null,
      "rfc": "SDC200101AB1",
      "fecha_inicio_operaciones": null,
      "num_empleados": null,
      "domicilio_en_yucatan": null,
      "redes_sociales": []
    },
    "producto_servicio": {
      "mercado_meta": "Restaurantes y hoteles de la península.",
      "tiempo_vida_anaquel": null,
      "requisitos_alimentos": [],
      "apoyo_requisitos": null,
      "requiere_refrigeracion": null,
      "requiere_congelacion": null
    }
  }
}
```

404: `{ "message": "No query results for model [App\\Models\\Restaurantero] 999999" }` (inexistente) o
`{ "message": "Proveedor no encontrado." }` (inactivo).

### GET /proveedores/{id}/disponibilidad

Los horarios que ofrece la ficha web para agendar, con el estado de cada uno **ya calculado por el servidor** con
las mismas reglas que aplica `POST /citas`. La app no calcula nada: pinta `horarios` y habilita solo los `libre`
cuando `puede_agendar` es `true`.

Query (opcional): `fecha=YYYY-MM-DD` **o** `desde=YYYY-MM-DD&hasta=YYYY-MM-DD`. Sin filtros: todos los días del
evento.

- **Evento**: el encuentro en el que el comprador está aprobado. Si no lo está, el evento principal (como la ficha
  web), con `puede_agendar: false` y el `motivo`.
- **Horarios**: cada día del evento, de la hora de inicio a la de fin del evento, cada `intervalo_minutos`
  (= `tiempo_entre_citas_minutos` redondeado a 5). Cada cita dura `duracion_cita_minutos`.
- `estado` de cada horario:
  - `libre`
  - `ocupado_proveedor`: el proveedor ya tiene una cita activa que se cruza. Los horarios **pegados** a una cita
    del proveedor quedan libres (el proveedor no tiene colchón).
  - `bloqueado_por_tus_citas`: se cruza con una cita activa del comprador (con cualquier proveedor) contando
    `colchon_minutos` (10) antes y después.
  - `fuera_de_ventana`: ya pasó, o la ventana de agendado del comprador está cerrada (`evento.ventana_agendado`).
- `mi_cita_id`: id de la cita propia que cae exactamente en ese horario (con este u otro proveedor), o `null`.
- `puede_agendar` / `motivo`: lo que respondería `POST /citas` aunque el horario esté libre (no aprobado,
  administrador, ventana cerrada, proveedor sin servicio, cupo lleno). Las citas canceladas y rechazadas no cuentan
  para `citas_usadas` ni ocupan horario.

200 (sin filtros; comprador de prueba con citas a las 10:00 con este proveedor, 10:40 y 13:00 con otros):
`HTTP 200`
```json
{
  "data": {
    "proveedor": {
      "id": 13,
      "nombre_negocio": "Salsas Doña Chela"
    },
    "evento": {
      "id": 5,
      "nombre": "Encuentro de Negocios Impúlsate (demo app)",
      "fecha_hora_inicio": "2026-10-06T09:00:00-06:00",
      "fecha_hora_fin": "2026-10-06T18:00:00-06:00",
      "ventana_agendado": {
        "inicio": "2026-09-17T14:07:54-06:00",
        "fin": "2026-10-06T18:00:00-06:00",
        "abierta": true
      },
      "tiempo_entre_citas_minutos": 30,
      "max_citas_por_comprador": 5
    },
    "duracion_cita_minutos": 30,
    "intervalo_minutos": 30,
    "colchon_minutos": 10,
    "citas_usadas": 4,
    "max_citas": 5,
    "citas_restantes": 1,
    "puede_agendar": true,
    "motivo": null,
    "dias": [
      {
        "fecha": "2026-10-06",
        "horarios": [
          {
            "hora": "09:00",
            "inicio": "2026-10-06T09:00:00-06:00",
            "fin": "2026-10-06T09:30:00-06:00",
            "estado": "libre",
            "mi_cita_id": null
          },
          {
            "hora": "09:30",
            "inicio": "2026-10-06T09:30:00-06:00",
            "fin": "2026-10-06T10:00:00-06:00",
            "estado": "bloqueado_por_tus_citas",
            "mi_cita_id": null
          },
          {
            "hora": "10:00",
            "inicio": "2026-10-06T10:00:00-06:00",
            "fin": "2026-10-06T10:30:00-06:00",
            "estado": "ocupado_proveedor",
            "mi_cita_id": 27
          },
          {
            "hora": "10:30",
            "inicio": "2026-10-06T10:30:00-06:00",
            "fin": "2026-10-06T11:00:00-06:00",
            "estado": "bloqueado_por_tus_citas",
            "mi_cita_id": 28
          },
          {
            "hora": "11:00",
            "inicio": "2026-10-06T11:00:00-06:00",
            "fin": "2026-10-06T11:30:00-06:00",
            "estado": "ocupado_proveedor",
            "mi_cita_id": 28
          },
          {
            "hora": "11:30",
            "inicio": "2026-10-06T11:30:00-06:00",
            "fin": "2026-10-06T12:00:00-06:00",
            "estado": "libre",
            "mi_cita_id": null
          },
          {
            "hora": "12:00",
            "inicio": "2026-10-06T12:00:00-06:00",
            "fin": "2026-10-06T12:30:00-06:00",
            "estado": "libre",
            "mi_cita_id": null
          },
          {
            "hora": "12:30",
            "inicio": "2026-10-06T12:30:00-06:00",
            "fin": "2026-10-06T13:00:00-06:00",
            "estado": "bloqueado_por_tus_citas",
            "mi_cita_id": null
          }
        ]
      }
    ]
  }
}
```
_Recortado: el día trae 18 horarios (09:00 a 17:30); se muestran los primeros 8._

200 con el cupo lleno (`motivo`):
`HTTP 200`
```json
{
  "data": {
    "proveedor": {
      "id": 9,
      "nombre_negocio": "Limpieza Peninsular"
    },
    "evento": {
      "id": 5,
      "nombre": "Encuentro de Negocios Impúlsate (demo app)",
      "fecha_hora_inicio": "2026-10-06T09:00:00-06:00",
      "fecha_hora_fin": "2026-10-06T18:00:00-06:00",
      "ventana_agendado": {
        "inicio": "2026-09-17T14:04:09-06:00",
        "fin": "2026-10-06T18:00:00-06:00",
        "abierta": true
      },
      "tiempo_entre_citas_minutos": 30,
      "max_citas_por_comprador": 5
    },
    "duracion_cita_minutos": 30,
    "intervalo_minutos": 30,
    "colchon_minutos": 10,
    "citas_usadas": 5,
    "max_citas": 5,
    "citas_restantes": 0,
    "puede_agendar": false,
    "motivo": "Has alcanzado el límite de 5 citas en este evento.",
    "dias": [
      {
        "fecha": "2026-10-06",
        "horarios": [
          {
            "hora": "09:00",
            "inicio": "2026-10-06T09:00:00-06:00",
            "fin": "2026-10-06T09:30:00-06:00",
            "estado": "libre",
            "mi_cita_id": null
          }
        ]
      }
    ]
  }
}
```
_Recortado: el día trae 18 horarios (09:00 a 17:30); se muestran los primeros 1._

422:
`HTTP 422`
```json
{
  "message": "El campo fecha no corresponde con el formato Y-m-d.",
  "errors": {
    "fecha": [
      "El campo fecha no corresponde con el formato Y-m-d."
    ]
  }
}
```

---

## 9. Citas del comprador

### Objeto `cita` (CitaResource)

| Campo | Tipo | Nota |
|---|---|---|
| `id` | int | |
| `inicio`, `fin` | ISO 8601 | |
| `estado` | string | `pendiente`, `confirmada`, `pendiente_reconfirmacion`, `reagendada`, `rechazada`, `cancelada`, `completada` |
| `estado_texto` | string | etiqueta para mostrar |
| `notas`, `productos_interes` | string\|null, string[] | |
| `mesa` | string\|null | asignada por el admin el día del evento |
| `evento` | `{id, nombre}` | |
| `proveedor` | `{id, nombre_negocio, categoria, logo_url, municipio}` | |
| `comprador` | `{id, nombre, nombre_empresa}` | **solo** si quien mira es el proveedor de la cita o un admin (Fase 3) |
| `propuesta_reagenda` | `{inicio, fin}`\|null | solo con `estado = pendiente_reconfirmacion` |
| `puede_cancelar` | bool | dueño y estado distinto de `completada`/`cancelada` (misma regla que la web) |
| `puede_editar_notas` | bool | dueño |
| `puede_responder_reagenda` | bool | dueño y hay propuesta pendiente |
| `creada_at` | ISO 8601 | |

### POST /citas

Límite `api-agendar` (10/min). Body JSON (mismas reglas que el formulario web):

| Campo | Reglas |
|---|---|
| `restaurantero_id` | requerido, id de proveedor existente |
| `fecha` | requerido, fecha `YYYY-MM-DD`, hoy o después |
| `hora` | requerido, `HH:MM` (uno de los `horarios[].hora` de la disponibilidad) |
| `notas` | opcional, máx. 1000 |
| `productos_interes` | opcional, arreglo de textos (máx. 255 c/u); normalmente `productos[].nombre` de la ficha |

La cita nace `pendiente` (el proveedor la acepta o rechaza). Se envían los mismos correos y notificaciones que en
la web. Dos compradores que piden el mismo hueco a la vez: uno recibe 201 y el otro 422 (probado en paralelo contra
Apache: `201` + `422 "Este horario ya no está disponible con el proveedor..."`, una sola cita en BD).

201:
`HTTP 201`
```json
{
  "data": {
    "id": 33,
    "inicio": "2026-10-06T12:00:00-06:00",
    "fin": "2026-10-06T12:30:00-06:00",
    "estado": "pendiente",
    "estado_texto": "Pendiente",
    "notas": "Me interesa el recado rojo para 3 sucursales.",
    "productos_interes": [
      "Recado rojo 1 kg"
    ],
    "mesa": null,
    "evento": {
      "id": 5,
      "nombre": "Encuentro de Negocios Impúlsate (demo app)"
    },
    "proveedor": {
      "id": 13,
      "nombre_negocio": "Salsas Doña Chela",
      "categoria": "Alimentos y Bebidas",
      "logo_url": "http://localhost/citas/public/storage/logos/demo-proveedor-prueba.png",
      "municipio": "Mérida"
    },
    "propuesta_reagenda": null,
    "puede_cancelar": true,
    "puede_editar_notas": true,
    "puede_responder_reagenda": false,
    "creada_at": "2026-09-22T14:05:59-06:00"
  },
  "message": "Cita solicitada correctamente."
}
```

422 validación:
`HTTP 422`
```json
{
  "message": "El campo restaurantero id es obligatorio. (and 2 more errors)",
  "errors": {
    "restaurantero_id": [
      "El campo restaurantero id es obligatorio."
    ],
    "fecha": [
      "El campo fecha es obligatorio."
    ],
    "hora": [
      "El campo hora es obligatorio."
    ]
  }
}
```

422 reglas de negocio (el campo indica cuál):

| `errors.*` | Mensaje (ejemplos reales) |
|---|---|
| `fecha` | "Ya tienes una cita agendada a las 10:00 (se requieren 10 minutos de separación entre citas)." |
| `fecha` | "Este horario ya no está disponible con el proveedor. Por favor elige otro." |
| `fecha` | "El período de agendado aún no ha comenzado. Apertura: dd/mm/aaaa hh:mm." / "El período de agendado ya cerró (fin: ...)." / "Solo puedes agendar citas para el día del evento (...)." |
| `limit` | "Has alcanzado el límite de 5 citas en este evento." |
| `servicio` | "Este proveedor no tiene servicios disponibles." |
| `error` | "Debes estar registrado y aprobado en el evento para agendar citas." / "Tu solicitud de registro al evento está pendiente de aprobación. ..." / "El administrador no puede agendar citas para sí mismo. ..." |
| `edicion` | "No hay un encuentro de negocios activo. Contacta al administrador." |

`HTTP 422`
```json
{
  "message": "Ya tienes una cita agendada a las 10:00 (se requieren 10 minutos de separación entre citas).",
  "errors": {
    "fecha": [
      "Ya tienes una cita agendada a las 10:00 (se requieren 10 minutos de separación entre citas)."
    ]
  }
}
```

`HTTP 422`
```json
{
  "message": "Has alcanzado el límite de 5 citas en este evento.",
  "errors": {
    "limit": [
      "Has alcanzado el límite de 5 citas en este evento."
    ]
  }
}
```

### GET /citas

Mis citas como comprador, en orden cronológico (`inicio` ascendente), paginadas.

Query (opcional): `estado` (uno o varios separados por coma, p. ej. `pendiente,confirmada`), `evento_id`,
`desde=YYYY-MM-DD` (citas desde ese día), `per_page` (1–50, defecto 20). Un estado desconocido da 422 en `estado`.

200 (`?per_page=2`):
`HTTP 200`
```json
{
  "data": [
    {
      "id": 30,
      "inicio": "2026-08-22T12:00:00-06:00",
      "fin": "2026-08-22T12:30:00-06:00",
      "estado": "completada",
      "estado_texto": "Completada",
      "notas": "Cita de demostración para la app.",
      "productos_interes": [],
      "mesa": null,
      "evento": {
        "id": 7,
        "nombre": "Encuentro de Negocios Julio (demo app)"
      },
      "proveedor": {
        "id": 7,
        "nombre_negocio": "Textiles Xokbil",
        "categoria": "Textiles",
        "logo_url": null,
        "municipio": "Mérida"
      },
      "propuesta_reagenda": null,
      "puede_cancelar": false,
      "puede_editar_notas": true,
      "puede_responder_reagenda": false,
      "creada_at": "2026-09-22T13:35:33-06:00"
    }
  ],
  "links": {
    "first": "http://127.0.0.1/citas/public/api/v1/citas?per_page=2&page=1",
    "last": "http://127.0.0.1/citas/public/api/v1/citas?per_page=2&page=4",
    "prev": null,
    "next": "http://127.0.0.1/citas/public/api/v1/citas?per_page=2&page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 4,
    "links": "…",
    "path": "http://127.0.0.1/citas/public/api/v1/citas",
    "per_page": 2,
    "to": 2,
    "total": 7
  }
}
```
_Recortado: `data` traía 2 elementos; se muestra el primero; se omite `meta.links` (arreglo de enlaces de página)._

### GET /citas/{id}

200 para el comprador dueño (también para el proveedor de la cita y admins; ellos reciben además `comprador`).
403 si la cita es de otro. 404 si no existe.

200 (cita con reagenda por confirmar, `GET /citas?estado=pendiente_reconfirmacion` devuelve la misma):
`HTTP 200`
```json
{
  "data": {
    "id": 32,
    "inicio": "2026-10-06T13:00:00-06:00",
    "fin": "2026-10-06T13:30:00-06:00",
    "estado": "pendiente_reconfirmacion",
    "estado_texto": "Nuevo horario por confirmar",
    "notas": "Cita de demostración para la app.",
    "productos_interes": [],
    "mesa": null,
    "evento": {
      "id": 5,
      "nombre": "Encuentro de Negocios Impúlsate (demo app)"
    },
    "proveedor": {
      "id": 8,
      "nombre_negocio": "Artesanías Kanasín",
      "categoria": "Artesanías",
      "logo_url": null,
      "municipio": "Kanasín"
    },
    "propuesta_reagenda": {
      "inicio": "2026-10-06T14:00:00-06:00",
      "fin": "2026-10-06T14:30:00-06:00"
    },
    "puede_cancelar": true,
    "puede_editar_notas": true,
    "puede_responder_reagenda": true,
    "creada_at": "2026-09-22T14:04:08-06:00"
  }
}
```

403:
`HTTP 403`
```json
{
  "message": "This action is unauthorized."
}
```

### POST /citas/{id}/cancelar

Sin body. Misma regla y correo que la web (se avisa por correo al comprador). 403 si no es tu cita.

200:
`HTTP 200`
```json
{
  "data": {
    "id": 33,
    "inicio": "2026-10-06T12:00:00-06:00",
    "fin": "2026-10-06T12:30:00-06:00",
    "estado": "cancelada",
    "estado_texto": "Cancelada",
    "notas": "Llevar lista de precios mayoreo.",
    "productos_interes": [
      "Recado rojo 1 kg"
    ],
    "mesa": null,
    "evento": {
      "id": 5,
      "nombre": "Encuentro de Negocios Impúlsate (demo app)"
    },
    "proveedor": {
      "id": 13,
      "nombre_negocio": "Salsas Doña Chela",
      "categoria": "Alimentos y Bebidas",
      "logo_url": "http://localhost/citas/public/storage/logos/demo-proveedor-prueba.png",
      "municipio": "Mérida"
    },
    "propuesta_reagenda": null,
    "puede_cancelar": false,
    "puede_editar_notas": true,
    "puede_responder_reagenda": false,
    "creada_at": "2026-09-22T14:05:59-06:00"
  },
  "message": "Cita cancelada correctamente."
}
```

422 (cita completada o ya cancelada):
`HTTP 422`
```json
{
  "message": "No puedes cancelar esta cita.",
  "errors": {
    "error": [
      "No puedes cancelar esta cita."
    ]
  }
}
```

### PATCH /citas/{id}/notas

Body: `notas` (texto, máx. 1000; `null` o ausente **borra** las notas, igual que la web). 403 si no es tu cita.

200:
`HTTP 200`
```json
{
  "data": {
    "id": 33,
    "inicio": "2026-10-06T12:00:00-06:00",
    "fin": "2026-10-06T12:30:00-06:00",
    "estado": "pendiente",
    "estado_texto": "Pendiente",
    "notas": "Llevar lista de precios mayoreo.",
    "productos_interes": [
      "Recado rojo 1 kg"
    ],
    "mesa": null,
    "evento": {
      "id": 5,
      "nombre": "Encuentro de Negocios Impúlsate (demo app)"
    },
    "proveedor": {
      "id": 13,
      "nombre_negocio": "Salsas Doña Chela",
      "categoria": "Alimentos y Bebidas",
      "logo_url": "http://localhost/citas/public/storage/logos/demo-proveedor-prueba.png",
      "municipio": "Mérida"
    },
    "propuesta_reagenda": null,
    "puede_cancelar": true,
    "puede_editar_notas": true,
    "puede_responder_reagenda": false,
    "creada_at": "2026-09-22T14:05:59-06:00"
  },
  "message": "Notas actualizadas."
}
```

422:
`{ "message": "El campo notas no debe ser mayor que 1000 caracteres.", "errors": { "notas": ["..."] } }`

### POST /citas/{id}/reagenda/aceptar

Responde a la propuesta de nuevo horario del proveedor (lo mismo que el botón del correo). Límite `api-respuestas`
(10/min). Sin body. 403 si no es tu cita.

Si el hueco propuesto ya se ocupó mientras la propuesta esperaba, **la cita no cambia** (sigue en
`pendiente_reconfirmacion` con su horario original), se avisa al proveedor para que proponga otro y responde 422.

200:
`HTTP 200`
```json
{
  "data": {
    "id": 32,
    "inicio": "2026-10-06T14:00:00-06:00",
    "fin": "2026-10-06T14:30:00-06:00",
    "estado": "confirmada",
    "estado_texto": "Confirmada",
    "notas": "Cita de demostración para la app.",
    "productos_interes": [],
    "mesa": null,
    "evento": {
      "id": 5,
      "nombre": "Encuentro de Negocios Impúlsate (demo app)"
    },
    "proveedor": {
      "id": 8,
      "nombre_negocio": "Artesanías Kanasín",
      "categoria": "Artesanías",
      "logo_url": null,
      "municipio": "Kanasín"
    },
    "propuesta_reagenda": null,
    "puede_cancelar": true,
    "puede_editar_notas": true,
    "puede_responder_reagenda": false,
    "creada_at": "2026-09-22T14:04:08-06:00"
  },
  "message": "¡Cita confirmada con la nueva fecha! Nos vemos pronto."
}
```

422 horario ya ocupado:
`HTTP 422`
```json
{
  "message": "El horario propuesto ya no está disponible. Tu cita conserva su horario original; el proveedor te propondrá otro.",
  "errors": {
    "error": [
      "El horario propuesto ya no está disponible. Tu cita conserva su horario original; el proveedor te propondrá otro."
    ]
  }
}
```

422 sin propuesta pendiente (ya respondida o nunca hubo):
`HTTP 422`
```json
{
  "message": "Esta cita no tiene una propuesta de cambio de horario pendiente.",
  "errors": {
    "error": [
      "Esta cita no tiene una propuesta de cambio de horario pendiente."
    ]
  }
}
```

### POST /citas/{id}/reagenda/rechazar

La cita queda **cancelada** y se avisa al proveedor (correo + notificación), igual que el enlace del correo.
Límite `api-respuestas`. 403 si no es tu cita; 422 sin propuesta pendiente.

200:
`HTTP 200`
```json
{
  "data": {
    "id": 32,
    "inicio": "2026-10-06T13:00:00-06:00",
    "fin": "2026-10-06T13:30:00-06:00",
    "estado": "cancelada",
    "estado_texto": "Cancelada",
    "notas": "Cita de demostración para la app.",
    "productos_interes": [],
    "mesa": null,
    "evento": {
      "id": 5,
      "nombre": "Encuentro de Negocios Impúlsate (demo app)"
    },
    "proveedor": {
      "id": 8,
      "nombre_negocio": "Artesanías Kanasín",
      "categoria": "Artesanías",
      "logo_url": null,
      "municipio": "Kanasín"
    },
    "propuesta_reagenda": null,
    "puede_cancelar": false,
    "puede_editar_notas": true,
    "puede_responder_reagenda": false,
    "creada_at": "2026-09-22T14:04:08-06:00"
  },
  "message": "Has rechazado la propuesta. La cita quedó cancelada."
}
```

---

## 10. Agendas propuestas por el admin

El admin arma una agenda (varias citas con distintos proveedores) y se la envía al comprador. Solo se listan las
**enviadas** al comprador autenticado. Aceptar crea las citas **confirmadas** y avisa a cada proveedor; rechazar
avisa a los administradores. Es la misma lógica que el enlace del correo, que sigue funcionando.

`estado` ∈ `pendiente | aceptada | rechazada`. `citas[].cita_id` es la cita creada al aceptar (`null` antes).

### GET /agendas

Pendientes primero y después las más recientes; paginado (`per_page` 1–50).

200:
`HTTP 200`
```json
{
  "data": [
    {
      "id": 4,
      "estado": "pendiente",
      "estado_texto": "Pendiente de respuesta",
      "puede_responder": true,
      "evento": {
        "id": 5,
        "nombre": "Encuentro de Negocios Impúlsate (demo app)",
        "fecha_hora_inicio": "2026-10-06T09:00:00-06:00"
      },
      "enviada_at": "2026-09-22T14:06:51-06:00",
      "respondida_at": null,
      "citas": [
        {
          "id": 12,
          "inicio": "2026-10-06T15:00:00-06:00",
          "fin": "2026-10-06T15:30:00-06:00",
          "cita_id": null,
          "proveedor": {
            "id": 9,
            "nombre_negocio": "Limpieza Peninsular",
            "categoria": "Limpieza e higiene",
            "descripcion": "Limpieza Peninsular: proveedor local de Limpieza e higiene en Umán.",
            "logo_url": null,
            "municipio": "Umán"
          }
        },
        {
          "id": 13,
          "inicio": "2026-10-06T15:30:00-06:00",
          "fin": "2026-10-06T16:00:00-06:00",
          "cita_id": null,
          "proveedor": {
            "id": 10,
            "nombre_negocio": "Cristalería Progreso",
            "categoria": "Cristalería, plásticos y utensilios",
            "descripcion": "Cristalería Progreso: proveedor local de Cristalería, plásticos y utensilios en Progreso.",
            "logo_url": null,
            "municipio": "Progreso"
          }
        },
        {
          "id": 14,
          "inicio": "2026-10-06T16:00:00-06:00",
          "fin": "2026-10-06T16:30:00-06:00",
          "cita_id": null,
          "proveedor": {
            "id": 11,
            "nombre_negocio": "Licores de Xtabentún Izamal",
            "categoria": "Vinos y licores",
            "descripcion": "Licores de Xtabentún Izamal: proveedor local de Vinos y licores en Izamal.",
            "logo_url": null,
            "municipio": "Izamal"
          }
        }
      ]
    }
  ],
  "links": {
    "first": "http://127.0.0.1/citas/public/api/v1/agendas?page=1",
    "last": "http://127.0.0.1/citas/public/api/v1/agendas?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "links": "…",
    "path": "http://127.0.0.1/citas/public/api/v1/agendas",
    "per_page": 20,
    "to": 1,
    "total": 1
  }
}
```
_Recortado: se omite `meta.links` (arreglo de enlaces de página)._

### GET /agendas/{id}

403 si no es tuya o si el admin aún no la envía.

200:
`HTTP 200`
```json
{
  "data": {
    "id": 4,
    "estado": "pendiente",
    "estado_texto": "Pendiente de respuesta",
    "puede_responder": true,
    "evento": {
      "id": 5,
      "nombre": "Encuentro de Negocios Impúlsate (demo app)",
      "fecha_hora_inicio": "2026-10-06T09:00:00-06:00"
    },
    "enviada_at": "2026-09-22T14:06:51-06:00",
    "respondida_at": null,
    "citas": [
      {
        "id": 12,
        "inicio": "2026-10-06T15:00:00-06:00",
        "fin": "2026-10-06T15:30:00-06:00",
        "cita_id": null,
        "proveedor": {
          "id": 9,
          "nombre_negocio": "Limpieza Peninsular",
          "categoria": "Limpieza e higiene",
          "descripcion": "Limpieza Peninsular: proveedor local de Limpieza e higiene en Umán.",
          "logo_url": null,
          "municipio": "Umán"
        }
      },
      {
        "id": 13,
        "inicio": "2026-10-06T15:30:00-06:00",
        "fin": "2026-10-06T16:00:00-06:00",
        "cita_id": null,
        "proveedor": {
          "id": 10,
          "nombre_negocio": "Cristalería Progreso",
          "categoria": "Cristalería, plásticos y utensilios",
          "descripcion": "Cristalería Progreso: proveedor local de Cristalería, plásticos y utensilios en Progreso.",
          "logo_url": null,
          "municipio": "Progreso"
        }
      },
      {
        "id": 14,
        "inicio": "2026-10-06T16:00:00-06:00",
        "fin": "2026-10-06T16:30:00-06:00",
        "cita_id": null,
        "proveedor": {
          "id": 11,
          "nombre_negocio": "Licores de Xtabentún Izamal",
          "categoria": "Vinos y licores",
          "descripcion": "Licores de Xtabentún Izamal: proveedor local de Vinos y licores en Izamal.",
          "logo_url": null,
          "municipio": "Izamal"
        }
      }
    ]
  }
}
```

### POST /agendas/{id}/aceptar

Sin body. Límite `api-respuestas`. 403 ajena.

200:
`HTTP 200`
```json
{
  "data": {
    "id": 4,
    "estado": "aceptada",
    "estado_texto": "Aceptada",
    "puede_responder": false,
    "evento": {
      "id": 5,
      "nombre": "Encuentro de Negocios Impúlsate (demo app)",
      "fecha_hora_inicio": "2026-10-06T09:00:00-06:00"
    },
    "enviada_at": "2026-09-22T14:06:51-06:00",
    "respondida_at": "2026-09-22T14:06:54-06:00",
    "citas": [
      {
        "id": 12,
        "inicio": "2026-10-06T15:00:00-06:00",
        "fin": "2026-10-06T15:30:00-06:00",
        "cita_id": 36,
        "proveedor": {
          "id": 9,
          "nombre_negocio": "Limpieza Peninsular",
          "categoria": "Limpieza e higiene",
          "descripcion": "Limpieza Peninsular: proveedor local de Limpieza e higiene en Umán.",
          "logo_url": null,
          "municipio": "Umán"
        }
      },
      {
        "id": 13,
        "inicio": "2026-10-06T15:30:00-06:00",
        "fin": "2026-10-06T16:00:00-06:00",
        "cita_id": 37,
        "proveedor": {
          "id": 10,
          "nombre_negocio": "Cristalería Progreso",
          "categoria": "Cristalería, plásticos y utensilios",
          "descripcion": "Cristalería Progreso: proveedor local de Cristalería, plásticos y utensilios en Progreso.",
          "logo_url": null,
          "municipio": "Progreso"
        }
      },
      {
        "id": 14,
        "inicio": "2026-10-06T16:00:00-06:00",
        "fin": "2026-10-06T16:30:00-06:00",
        "cita_id": 38,
        "proveedor": {
          "id": 11,
          "nombre_negocio": "Licores de Xtabentún Izamal",
          "categoria": "Vinos y licores",
          "descripcion": "Licores de Xtabentún Izamal: proveedor local de Vinos y licores en Izamal.",
          "logo_url": null,
          "municipio": "Izamal"
        }
      }
    ]
  },
  "message": "¡Agenda aceptada! Tus citas han sido registradas en el sistema."
}
```

422 ya respondida:
`HTTP 422`
```json
{
  "message": "Esta propuesta de agenda ya fue respondida.",
  "errors": {
    "error": [
      "Esta propuesta de agenda ya fue respondida."
    ]
  }
}
```

422 si algún proveedor ya no tiene servicio activo: "Uno de los proveedores de esta agenda no tiene un servicio
activo para agendar citas." (`errors.error`); la agenda sigue pendiente.

### POST /agendas/{id}/rechazar

Sin body. Límite `api-respuestas`. 422 si ya estaba respondida.

200:
`HTTP 200`
```json
{
  "data": {
    "id": 4,
    "estado": "rechazada",
    "estado_texto": "Rechazada",
    "puede_responder": false,
    "respondida_at": "2026-09-22T14:06:56-06:00",
    "…": "…"
  },
  "message": "Has rechazado la propuesta de agenda. El equipo de IMPULSATE te contactará pronto."
}
```
_Recortado: el resto de `data` es igual que en `GET /agendas/{id}`._

---

## 11. Encuestas de satisfacción

Encuestas que el admin envió al usuario al terminar un evento (las de prueba no aparecen). Responder por la app
equivale a responder desde el enlace del correo.

### GET /encuestas/pendientes

Sin paginar.

200:
`HTTP 200`
```json
{
  "data": [
    {
      "id": 3,
      "tipo": "comprador",
      "evento": {
        "id": 7,
        "nombre": "Encuentro de Negocios Julio (demo app)",
        "sector_economico": "Alimentos y Bebidas"
      },
      "completada": false,
      "completada_at": null,
      "enviada_at": "2026-09-22T14:04:08-06:00"
    }
  ]
}
```

### GET /encuestas/{id}

403 si no es tuya. Trae `preguntas[]` con la forma que interpreta la web:

| Campo | Nota |
|---|---|
| `id` | clave con la que se envía la respuesta |
| `tipo` | `escala` (entero entre `escala_min` y `escala_max`), `nps` (0–10), `binario` (uno de `valores`: "Sí"/"No"), `opciones` (una de `opciones`), `multiple` (arreglo con elementos de `opciones`), `texto` (máx. 2000) |
| `requerida` | `true` salvo que la plantilla diga lo contrario |
| `condicion` | opcional `{pregunta_id, operador, valor}`: la pregunta **solo se muestra** si la respuesta a `pregunta_id` cumple `igual`/`diferente` (texto) o `mayor`/`menor` (número) respecto a `valor`. Sin respuesta en la fuente, la pregunta se oculta. |

200 (plantilla real de 19 preguntas):
`HTTP 200`
```json
{
  "data": {
    "id": 3,
    "tipo": "comprador",
    "evento": {
      "id": 7,
      "nombre": "Encuentro de Negocios Julio (demo app)",
      "sector_economico": "Alimentos y Bebidas"
    },
    "completada": false,
    "completada_at": null,
    "enviada_at": "2026-09-22T14:04:08-06:00",
    "preguntas": [
      {
        "id": "correo",
        "tipo": "texto",
        "texto": "Correo electrónico",
        "requerida": true
      },
      {
        "id": "perfil",
        "tipo": "opciones",
        "texto": "Perfil de participación en este encuentro:",
        "requerida": true,
        "opciones": [
          "Comprador",
          "Proveedor"
        ]
      },
      {
        "id": "c_oferta_coincide",
        "tipo": "opciones",
        "texto": "¿La oferta de los proveedores asignados coincidió con las necesidades actuales de tu negocio?",
        "requerida": false,
        "opciones": [
          "Sí, totalmente.",
          "Parcialmente (algunos perfiles no encajaban).",
          "No, no era lo que buscaba."
        ],
        "condicion": {
          "pregunta_id": "perfil",
          "operador": "igual",
          "valor": "Comprador"
        }
      },
      {
        "id": "p_proyeccion",
        "tipo": "opciones",
        "texto": "Proyección económica: ¿A cuánto asciende el monto estimado de ventas que esperas cerrar a mediano plazo derivado de estas citas? (Dato confidencial para estadística interna del IYEM)",
        "requerida": true,
        "opciones": [
          "$0 (No se prevén cierres)",
          "Menos de $10,000 MXN",
          "De $10,000 a $50,000 MXN",
          "Más de $50,000 MXN"
        ],
        "condicion": {
          "pregunta_id": "perfil",
          "operador": "igual",
          "valor": "Proveedor"
        }
      },
      {
        "id": "p_monto_aproximado",
        "tipo": "texto",
        "texto": "En caso de venta, ¿nos podría compartir un monto aproximado?",
        "requerida": true,
        "condicion": {
          "pregunta_id": "p_proyeccion",
          "operador": "diferente",
          "valor": "$0 (No se prevén cierres)"
        }
      }
    ]
  }
}
```
_Recortado: 19 preguntas; se muestran 5 representativas (requerida, opciones, condicionada por igual y por diferente)._

### POST /encuestas/{id}/respuestas

Body JSON: un campo por pregunta, con su `id` como clave. Límite `api-encuestas` (20/min).

Además de la validación por tipo que hace la web, el servidor exige las preguntas **visibles y requeridas**
(la misma regla que en la web aplica el navegador), con el mensaje "Este campo es requerido.". Las respuestas vacías
no se guardan.

200:
`HTTP 200`
```json
{
  "data": {
    "id": 3,
    "tipo": "comprador",
    "evento": {
      "id": 7,
      "nombre": "Encuentro de Negocios Julio (demo app)",
      "sector_economico": "Alimentos y Bebidas"
    },
    "completada": true,
    "completada_at": "2026-09-22T14:07:43-06:00",
    "enviada_at": "2026-09-22T14:04:08-06:00"
  },
  "message": "¡Gracias por responder la encuesta!"
}
```

422 falta una requerida:
`HTTP 422`
```json
{
  "message": "Este campo es requerido.",
  "errors": {
    "correo": [
      "Este campo es requerido."
    ]
  }
}
```

422 valor fuera de las opciones:
`HTTP 422`
```json
{
  "message": "El campo cal org staff seleccionado no es válido.",
  "errors": {
    "cal_org_staff": [
      "El campo cal org staff seleccionado no es válido."
    ]
  }
}
```

422 ya respondida:
`HTTP 422`
```json
{
  "message": "Esta encuesta ya fue respondida.",
  "errors": {
    "error": [
      "Esta encuesta ya fue respondida."
    ]
  }
}
```

403 si no es tuya.

---

## 12. Proveedor (Fase 3)

Todo lo del PROVEEDOR vive bajo `/api/v1/proveedor/*`. Las respuestas de esta sección se capturaron el 2026-09-22
contra Apache (`http://192.168.10.6/citas/public/api/v1`) con `proveedor@prueba.impulsate.mx` y
`proveedor.nuevo@prueba.impulsate.mx` tras `php artisan impulsate:demo-movil`.

**Acceso.** Solo cuentas con el rol `restaurantero` (= proveedor), igual que el `role:restaurantero` de la web. No
depende de `rol_activo`: una cuenta con los dos roles usa estas rutas sin cambiar de rol. No se exige el perfil de
negocio completo (la web tampoco lo exige en `/proveedor/*`): un proveedor con perfil incompleto puede ver su panel,
completar el perfil y responder citas.

- Sin token → **401** `{"message": "Unauthenticated."}`.
- Cuenta sin rol de proveedor (p. ej. un comprador) → **403**:

`HTTP 403`
```json
{
  "message": "Esta sección es solo para proveedores."
}
```

- Cita de otro proveedor → **403** `{"message": "This action is unauthorized."}`; cita inexistente → **404**.
- Los productos no llevan id de negocio en la ruta: siempre se opera sobre el negocio del usuario autenticado, así que
  no hay forma de tocar los de otro proveedor.

**Límites** (nuevos, se suman a los de Generalidades):

| Limitador | Rutas | Límite |
|---|---|---|
| `api-proveedor-perfil` | `PUT /proveedor/perfil`, `PUT /proveedor/perfil/{seccion}`, `POST /proveedor/logo`, altas/ediciones/bajas de `/proveedor/productos` | 20/min por usuario |
| `api-proveedor-citas` | `POST /proveedor/citas/{id}/aceptar\|rechazar\|reagendar` | 30/min por usuario |

> **Importante — cada guardado del perfil re-dispara la aprobación**, igual que en la web: al guardar (cualquier
> sección, logo o producto) el negocio pasa a "solicitud de aprobación" y, si el perfil está completo, se
> auto-aprueba y se manda el correo "Proveedor aprobado" + avisos a los admins. Ver el aviso de estado en
> [PUT /proveedor/perfil/{seccion}](#put-proveedorperfilseccion): la app debe mostrar `data.negocio.estado`, no
> deducirlo del `message`.

### Objeto `negocio`

Aparece en panel, perfil, productos e inicio.

| Campo | Tipo | Nota |
|---|---|---|
| `id` | int\|null | null si aún no existe el negocio |
| `nombre_negocio` | string\|null | |
| `estado` | string | `aprobado`, `pendiente`, `rechazado` o `sin_perfil` |
| `motivo_rechazo` | string\|null | solo con `estado = rechazado` |
| `activo` | bool | |
| `visible_en_directorio` | bool | `activo && aprobado` (lo que usa el directorio público) |
| `perfil_completo` | bool | mismo criterio que la web (`Restaurantero::calcularPerfilCompleto`) |
| `porcentaje` | int 0–100 | la barra del panel web |
| `logo_url` | string\|null | URL absoluta |
| `solicitado_aprobacion_at` | ISO 8601\|null | último guardado del perfil |

### Objeto `cita` del proveedor (CitaProveedorResource)

| Campo | Tipo | Nota |
|---|---|---|
| `id`, `inicio`, `fin`, `estado`, `estado_texto`, `notas`, `productos_interes`, `mesa`, `evento`, `creada_at` | | igual que en la cita del comprador |
| `comprador` | `{id, nombre, email, telefono, nombre_empresa, necesidades}` | lo que la web le muestra al proveedor (nombre, correo, teléfono, necesidades) + empresa. **Nunca** CURP, RFC, documentos ni rutas |
| `propuesta_reagenda` | `{inicio, fin}`\|null | la propuesta que TÚ enviaste y el comprador aún no responde (`estado = pendiente_reconfirmacion`) |
| `puede_aceptar`, `puede_rechazar` | bool | `estado = pendiente` (los botones de la web) |
| `puede_reagendar` | bool | `estado` ∈ `pendiente`, `confirmada`, `reagendada` |

---

### GET /proveedor/panel

Resumen del panel web (`/proveedor/panel`). Igual que la web, **la primera visita crea el negocio vacío** (no aprobado,
con su servicio y horarios por defecto) si la cuenta aún no tiene uno.

| Campo | Contenido |
|---|---|
| `negocio` | objeto `negocio` |
| `perfil` | `{completo, porcentaje, faltantes[], secciones_faltantes[]}` — `faltantes` como en `GET /proveedor/perfil` |
| `kpis` | `citas_hoy`, `citas_semana` (de hoy al domingo), `citas_pendientes`, `total_en_evento` (todas menos canceladas/rechazadas), `tasa_aceptacion` (% confirmadas sobre confirmadas+rechazadas, `null` sin datos) |
| `contadores` | `{pendientes, confirmadas, rechazadas}` — los mismos de las pestañas de `/proveedor/citas` |
| `cita_proxima_2h` | cita pendiente/confirmada que empieza en las próximas 2 h, o `null` (aviso del panel web) |
| `proximas_citas` | hasta 5 citas futuras vigentes (`pendiente`, `confirmada`, `reagendada`, `pendiente_reconfirmacion`) |
| `eventos` | `EventoResource[]` (activos + en los que tiene registro), **calculados como proveedor** aunque la cuenta tenga los dos roles y esté en modo comprador: `tipo_registro = proveedor` en encuentros y `expositor` en bazares |
| `requisitos_bazar` | `{tiene_ine, tiene_csf, csf_fecha, csf_vigente, csf_vigencia_meses}` — lo que exige el registro al bazar (INE + CSF de máx. 3 meses). Los documentos se suben con `POST /perfil/documentos` |
| `notificaciones_sin_leer` | int |

La web **no** muestra al proveedor las visitas a su ficha (solo el admin en Métricas), así que no se exponen.

`HTTP 200`
```json
{
  "data": {
    "negocio": {
      "id": 13,
      "nombre_negocio": "Salsas Doña Chela",
      "estado": "aprobado",
      "motivo_rechazo": null,
      "activo": true,
      "visible_en_directorio": true,
      "perfil_completo": true,
      "porcentaje": 100,
      "logo_url": "http://192.168.10.6/citas/public/storage/logos/demo-proveedor-prueba.png",
      "solicitado_aprobacion_at": "2026-09-22T14:43:52-06:00"
    },
    "perfil": {
      "completo": true,
      "porcentaje": 100,
      "faltantes": [],
      "secciones_faltantes": []
    },
    "kpis": {
      "citas_hoy": 0,
      "citas_semana": 0,
      "citas_pendientes": 2,
      "total_en_evento": 5,
      "tasa_aceptacion": 67
    },
    "contadores": {
      "pendientes": 2,
      "confirmadas": 3,
      "rechazadas": 1
    },
    "cita_proxima_2h": null,
    "proximas_citas": [
      {
        "id": 27,
        "inicio": "2026-10-06T10:00:00-06:00",
        "fin": "2026-10-06T10:30:00-06:00",
        "estado": "confirmada",
        "estado_texto": "Confirmada",
        "notas": "Cita de demostración para la app.",
        "productos_interes": [],
        "mesa": "Mesa 3",
        "evento": {
          "id": 5,
          "nombre": "Encuentro de Negocios Impúlsate (demo app)"
        },
        "comprador": {
          "id": 14,
          "nombre": "Comprador de Prueba",
          "email": "comprador@prueba.impulsate.mx",
          "telefono": null,
          "nombre_empresa": "Empresa de Prueba",
          "necesidades": null
        },
        "propuesta_reagenda": null,
        "puede_aceptar": false,
        "puede_rechazar": false,
        "puede_reagendar": true,
        "creada_at": "2026-09-22T13:35:33-06:00"
      },
      {
        "id": 31,
        "inicio": "2026-10-06T11:00:00-06:00",
        "fin": "2026-10-06T11:30:00-06:00",
        "estado": "pendiente",
        "estado_texto": "Pendiente",
        "notas": "Cita de demostración para la app.",
        "productos_interes": [],
        "mesa": null,
        "evento": {
          "id": 5,
          "nombre": "Encuentro de Negocios Impúlsate (demo app)"
        },
        "comprador": {
          "id": 24,
          "nombre": "Restaurante La Tradición",
          "email": "demo.comprador1@prueba.impulsate.mx",
          "telefono": "9992223344",
          "nombre_empresa": "Restaurante La Tradición",
          "necesidades": "Salsas, recados y miel para el menú de temporada."
        },
        "propuesta_reagenda": null,
        "puede_aceptar": true,
        "puede_rechazar": true,
        "puede_reagendar": true,
        "creada_at": "2026-09-22T13:35:33-06:00"
      }
    ],
    "eventos": [
      {
        "id": 5,
        "nombre": "Encuentro de Negocios Impúlsate (demo app)",
        "descripcion": "Encuentro de negocios entre compradores del sector restaurantero y proveedores locales de Yucatán.",
        "sector_economico": "Alimentos y Bebidas",
        "tipo_evento": "encuentro_negocios",
        "imagen_url": null,
        "convocatoria_url": null,
        "fecha_hora_inicio": "2026-10-06T09:00:00-06:00",
        "fecha_hora_fin": "2026-10-06T18:00:00-06:00",
        "estado": "activo",
        "destacado": true,
        "fases": [
          {
            "clave": "registro_proveedores",
            "titulo": "Registro de proveedores",
            "inicio": "2026-09-17T14:45:27-06:00",
            "fin": "2026-10-04T09:00:00-06:00",
            "estado": "en_curso"
          },
          {
            "clave": "registro_compradores",
            "titulo": "Registro de compradores",
            "inicio": "2026-09-17T14:45:27-06:00",
            "fin": "2026-10-05T09:00:00-06:00",
            "estado": "en_curso"
          },
          {
            "clave": "evento",
            "titulo": "Evento",
            "inicio": "2026-10-06T09:00:00-06:00",
            "fin": "2026-10-06T18:00:00-06:00",
            "estado": "pendiente"
          }
        ],
        "mi_registro": {
          "comprador": null,
          "proveedor": {
            "estado": "aprobado",
            "motivo_rechazo": null,
            "registrado_at": "2026-09-22T14:45:27-06:00"
          },
          "expositor": null
        },
        "tipo_registro": "proveedor",
        "puede_registrarse": false,
        "motivo_bloqueo": null,
        "accion": "ver_proveedores"
      }
    ],
    "requisitos_bazar": {
      "tiene_ine": false,
      "tiene_csf": false,
      "csf_fecha": null,
      "csf_vigente": false,
      "csf_vigencia_meses": 3
    },
    "notificaciones_sin_leer": 5
  }
}
```
_Recortado: `proximas_citas` traía 5; se muestran 2; `eventos` traía 3 (`EventoResource`, ver sección 7); se muestra el encuentro._

### GET /proveedor/calendario

Las citas del calendario del panel (el feed de FullCalendar de la web: **todas menos las canceladas**, incluidas las
rechazadas en gris). No hay bloqueos de agenda en la web: solo citas.

Query opcional: `desde`, `hasta` (`YYYY-MM-DD`, ambos inclusive; `hasta ≥ desde`). Sin rango devuelve todas.
Colores (los de la web): pendiente `#f59e0b`, confirmada `#10b981`, completada `#3b82f6`, cualquier otro `#6b7280`.

`HTTP 200`
```json
{
  "data": [
    {
      "id": 27,
      "titulo": "Comprador de Prueba",
      "inicio": "2026-10-06T10:00:00-06:00",
      "fin": "2026-10-06T10:30:00-06:00",
      "estado": "confirmada",
      "estado_texto": "Confirmada",
      "color": "#10b981",
      "evento": {
        "id": 5,
        "nombre": "Encuentro de Negocios Impúlsate (demo app)"
      },
      "comprador": {
        "id": 14,
        "nombre": "Comprador de Prueba",
        "nombre_empresa": "Empresa de Prueba"
      }
    },
    {
      "id": 31,
      "titulo": "Restaurante La Tradición",
      "inicio": "2026-10-06T11:00:00-06:00",
      "fin": "2026-10-06T11:30:00-06:00",
      "estado": "pendiente",
      "estado_texto": "Pendiente",
      "color": "#f59e0b",
      "evento": {
        "id": 5,
        "nombre": "Encuentro de Negocios Impúlsate (demo app)"
      },
      "comprador": {
        "id": 24,
        "nombre": "Restaurante La Tradición",
        "nombre_empresa": "Restaurante La Tradición"
      }
    }
  ],
  "meta": {
    "desde": "2026-10-06",
    "hasta": "2026-10-06",
    "total": 6
  }
}
```
_Recortado: `data` traía 6 elementos; se muestran 2._

`HTTP 422`
```json
{
  "message": "El campo hasta debe ser una fecha posterior o igual a desde.",
  "errors": {
    "hasta": [
      "El campo hasta debe ser una fecha posterior o igual a desde."
    ]
  }
}
```

> **Feed web en producción (09-09):** la ruta `GET /proveedor/panel/eventos` existe y responde bien; el 404 venía de
> `Restaurantero/Panel.vue`, que pedía la URL fija `/restaurantero/panel/eventos` (el prefijo real es `/proveedor`).
> El arreglo (`route('restaurantero.panel.eventos')`) está en la copia de trabajo de `Panel.vue`, sin commitear ni
> desplegar.

---

### Perfil de negocio: cómo guarda la web y cómo guarda la API

La web (`/user/profile`, vista Proveedor) tiene 4 secciones de datos (+ Documentos, que ya existe en `/perfil/documentos`)
y **cada sección envía el formulario completo** a `POST /proveedor/completar-perfil`. Solo `nombre_restaurante` es
obligatorio para guardar; el resto se puede guardar incompleto ("Progreso guardado…"). No hay guardado por pasos.

La API reproduce eso sin relajar reglas: toma lo que la web precargaría en el formulario, le aplica los campos que envía
la app y lo guarda con **el mismo servicio y las mismas reglas** que la web. Por eso un guardado parcial (una sección)
es válido, y valida también los campos ya guardados de las demás secciones.

- `PUT /proveedor/perfil/{seccion}` con `seccion` ∈ `negocio`, `oferta`, `entrega`, `contacto`: solo se aplican los
  campos de esa sección (los demás del body se ignoran). Pensado para el wizard.
- `PUT /proveedor/perfil`: cualquier campo de cualquier sección.
- Logo: `POST /proveedor/logo`. Productos: `/proveedor/productos` (en la web van dentro de la sección "Productos y
  servicios"; aquí tienen endpoints propios para no mandar el listado completo con fotos).

Limpiezas automáticas (las de la web):

- `tipo_oferta = servicio` → se vacían los campos de producto (`tiempo_vida_anaquel`, certificaciones de producto,
  `apoyo_iyem*`, refrigeración/congelación = `false`). `tipo_oferta = producto` → se vacían los de servicio.
- `acepta_credito = false` → `credito_monto_maximo`, `credito_tiempo_*` = `null` y `credito_a_negociar = false`.
- `factura = false` → `regimen_fiscal = null`.
- `entrega_domicilio = false` → `cobertura_entrega` y `forma_entrega` = `null`.
- `sitio_web` sin esquema → se antepone `https://`.
- Un booleano ausente o `null` se guarda como `false` en `entrega_domicilio`, `acepta_credito`, `credito_a_negociar`,
  `pago_contraentrega` y `factura`.

#### Tabla de campos

"Perfil completo" = lo que exige `calcularPerfilCompleto()` para auto-aprobar; el servidor lo informa en `faltantes`.

| Campo | Sección | Tipo | Requerido para guardar | Validación exacta (web) | Perfil completo / notas |
|---|---|---|---|---|---|
| `nombre_restaurante` | negocio | string | **sí** | `required, string, max:200` | sí |
| `razon_social` | negocio | string | no | `nullable, string, max:200` | |
| `nombre_representante` | negocio | string | no | `nullable, string, max:200` | |
| `curp_representante` | negocio | string | no | `nullable, string, max:18` | la web no tiene input (reenvía lo guardado) |
| `rfc` | negocio | string | no | `nullable, string, max:13` | obligatorio si `factura = true`. Precarga: el del negocio o el del usuario |
| `num_empleados` | negocio | int | no | `nullable, integer, min:0` | |
| `fecha_inicio_operaciones` | negocio | `YYYY-MM-DD` | no | `nullable, date` | |
| `municipio` | negocio | string (catálogo `municipios`) | no | `nullable, string, max:100` | sí |
| `categorias_json` | negocio | string[] (catálogo `categorias`) | no | `nullable, array` | la web lo marca con * pero no lo exige |
| `descripcion` | negocio | string | no | `nullable, string, max:2000` | sí |
| `mercado_meta` | negocio | string | no | `nullable, string, max:1000` | |
| `domicilio_en_yucatan` | negocio | bool | no | `nullable, boolean` | |
| `tipo_oferta` | oferta | `producto`\|`servicio`\|`ambos` | no (default `producto`) | `nullable, in:producto,servicio,ambos` | |
| `tiempo_vida_anaquel` | oferta | string | no | `nullable, string, max:500` | solo producto/ambos |
| `tiene_certificaciones_producto` | oferta | bool\|null | no | `nullable, boolean` | solo producto/ambos |
| `certificaciones_cumple` | oferta | string[] (catálogo `certificaciones`) | no | `nullable, array`; cada uno `string, max:100` | solo producto/ambos |
| `certificaciones_cumple_otros` | oferta | string | no | `nullable, string, max:1000` | solo producto/ambos |
| `apoyo_iyem` | oferta | string[] (catálogo `certificaciones`) | no | `nullable, array`; cada uno `string, max:100` | "¿En qué temas requiere apoyo del IYEM?" |
| `apoyo_iyem_otros` | oferta | string | no | `nullable, string, max:1000` | |
| `requiere_refrigeracion` | oferta | bool | no | `nullable, boolean` | solo producto/ambos |
| `requiere_congelacion` | oferta | bool | no | `nullable, boolean` | solo producto/ambos |
| `tiene_certificaciones_servicio` | oferta | bool\|null | no | `nullable, boolean` | solo servicio/ambos |
| `certificaciones_servicio_detalle` | oferta | string | no | `nullable, string, max:2000` | solo servicio/ambos |
| `entrega_domicilio` | entrega | bool | no | `nullable, boolean` | sí (siempre queda `true`/`false` al guardar) |
| `cobertura_entrega` | entrega | `local`\|`regional`\|`nacional` | **si `entrega_domicilio = true`** | `nullable, string, in:…, required_if entrega_domicilio` | sí, si entrega a domicilio |
| `forma_entrega` | entrega | `programada`\|`flexible` | **si `entrega_domicilio = true`** | `nullable, string, in:…, required_if entrega_domicilio` | sí, si entrega a domicilio |
| `pago_contraentrega` | entrega | bool | no | `nullable, boolean` | |
| `factura` | entrega | bool | no | `nullable, boolean` | |
| `regimen_fiscal` | entrega | string (sugeridos en `regimenes_fiscales`) | no | `nullable, string, max:100` (acepta cualquier texto) | obligatorio si `factura = true` |
| `acepta_credito` | entrega | bool | no | `nullable, boolean` | |
| `credito_monto_maximo` | entrega | number | no | `nullable, numeric, min:0` | obligatorio si `acepta_credito` y no `credito_a_negociar`. `0` se guarda como `null` |
| `credito_tiempo_cantidad` | entrega | int | no | `nullable, integer, min:1` | ídem |
| `credito_tiempo_unidad` | entrega | `dias`\|`semanas`\|`meses` | no (precarga `dias`) | `nullable, string, in:dias,semanas,meses` | ídem |
| `credito_a_negociar` | entrega | bool | no | `nullable, boolean` | si `true`, no se exigen monto ni plazo |
| `telefono` | contacto | string | no | `nullable, string, max:20` | sí. La web limita el input a 10 dígitos; el servidor no. Precarga: el del negocio o el del usuario |
| `sitio_web` | contacto | string | no | `nullable, string, max:200` | se antepone `https://` |
| `direccion` | contacto | string | no | `nullable, string, max:300` | no sale en la ficha pública |
| `foto` (logo) | contacto → `POST /proveedor/logo` | archivo | — | `image, mimes:jpg,jpeg,png,webp, max:8192` (KB) | sí |
| productos | oferta → `/proveedor/productos` | ver abajo | — | `productos: array, max:5` | al menos 1 con nombre |

No editables desde la web ni desde la API: `redes_sociales` (la web lo manda pero el servidor lo ignora),
`requisitos_alimentos` y `apoyo_requisitos` (no están en el formulario: **cada guardado los deja en `[]`**, igual
que en la web).

### GET /proveedor/perfil

`valores` trae lo que la web precarga en el formulario, agrupado por sección (vacíos como `null`). `faltantes` es la
lista de lo que impide el perfil completo con la sección donde se captura; `secciones[].completa` sirve para el
check del wizard.

`HTTP 200`
```json
{
  "data": {
    "negocio": {
      "id": 13,
      "nombre_negocio": "Salsas Doña Chela",
      "estado": "aprobado",
      "motivo_rechazo": null,
      "activo": true,
      "visible_en_directorio": true,
      "perfil_completo": true,
      "porcentaje": 100,
      "logo_url": "http://192.168.10.6/citas/public/storage/logos/demo-proveedor-prueba.png",
      "solicitado_aprobacion_at": "2026-09-22T14:43:52-06:00"
    },
    "faltantes": [],
    "secciones": [
      {
        "clave": "negocio",
        "etiqueta": "Mi negocio",
        "completa": true,
        "faltantes": []
      },
      {
        "clave": "oferta",
        "etiqueta": "Productos y servicios",
        "completa": true,
        "faltantes": []
      },
      {
        "clave": "entrega",
        "etiqueta": "Entrega y pago",
        "completa": true,
        "faltantes": []
      },
      {
        "clave": "contacto",
        "etiqueta": "Contacto y redes",
        "completa": true,
        "faltantes": []
      }
    ],
    "valores": {
      "negocio": {
        "nombre_restaurante": "Salsas Doña Chela",
        "razon_social": "Salsas Doña Chela SA de CV",
        "nombre_representante": "Graciela Pech",
        "curp_representante": null,
        "rfc": "SDC200101AB1",
        "num_empleados": null,
        "fecha_inicio_operaciones": null,
        "municipio": "Mérida",
        "categorias_json": [],
        "descripcion": "Salsas y recados artesanales yucatecos para restaurantes.",
        "mercado_meta": "Restaurantes y hoteles de la península.",
        "domicilio_en_yucatan": false
      },
      "oferta": {
        "tipo_oferta": "producto",
        "tiempo_vida_anaquel": null,
        "tiene_certificaciones_producto": false,
        "certificaciones_cumple": [],
        "certificaciones_cumple_otros": null,
        "apoyo_iyem": [],
        "apoyo_iyem_otros": null,
        "requiere_refrigeracion": false,
        "requiere_congelacion": false,
        "tiene_certificaciones_servicio": null,
        "certificaciones_servicio_detalle": null
      },
      "entrega": {
        "entrega_domicilio": true,
        "cobertura_entrega": "regional",
        "forma_entrega": "programada",
        "pago_contraentrega": true,
        "factura": true,
        "regimen_fiscal": "601 - General de Ley Personas Morales",
        "acepta_credito": true,
        "credito_monto_maximo": null,
        "credito_tiempo_cantidad": null,
        "credito_tiempo_unidad": "dias",
        "credito_a_negociar": true
      },
      "contacto": {
        "telefono": "9991112233",
        "sitio_web": "https://salsasdonachela.mx",
        "direccion": "Calle 60 x 55, Centro, Mérida"
      }
    },
    "logo_url": "http://192.168.10.6/citas/public/storage/logos/demo-proveedor-prueba.png",
    "productos": [
      {
        "indice": 0,
        "nombre": "Recado rojo 1 kg",
        "descripcion": "Achiote artesanal",
        "foto_url": null,
        "capacidad_cantidad": 500,
        "capacidad_unidad": "kilogramos"
      },
      {
        "indice": 1,
        "nombre": "Salsa de habanero 250 ml",
        "descripcion": "Picor alto",
        "foto_url": null,
        "capacidad_cantidad": 2000,
        "capacidad_unidad": "piezas"
      }
    ]
  }
}
```

Proveedor nuevo (perfil incompleto):

`HTTP 200`
```json
{
  "data": {
    "negocio": {
      "id": 18,
      "nombre_negocio": "Tortillería La Nueva (demo app)",
      "estado": "pendiente",
      "motivo_rechazo": null,
      "activo": false,
      "visible_en_directorio": false,
      "perfil_completo": false,
      "porcentaje": 42,
      "logo_url": null,
      "solicitado_aprobacion_at": "2026-09-22T14:44:03-06:00"
    },
    "faltantes": [
      {
        "campo": "descripcion",
        "etiqueta": "Descripción",
        "seccion": "negocio"
      },
      {
        "campo": "telefono",
        "etiqueta": "Teléfono",
        "seccion": "contacto"
      },
      {
        "campo": "logo",
        "etiqueta": "Logo",
        "seccion": "contacto"
      },
      {
        "campo": "productos",
        "etiqueta": "Al menos 1 producto",
        "seccion": "oferta"
      },
      {
        "campo": "entrega_domicilio",
        "etiqueta": "Logística (¿entrega a domicilio?)",
        "seccion": "entrega"
      }
    ],
    "secciones": [
      {
        "clave": "negocio",
        "etiqueta": "Mi negocio",
        "completa": false,
        "faltantes": [
          "descripcion"
        ]
      },
      {
        "clave": "oferta",
        "etiqueta": "Productos y servicios",
        "completa": false,
        "faltantes": [
          "productos"
        ]
      },
      {
        "clave": "entrega",
        "etiqueta": "Entrega y pago",
        "completa": false,
        "faltantes": [
          "entrega_domicilio"
        ]
      },
      {
        "clave": "contacto",
        "etiqueta": "Contacto y redes",
        "completa": false,
        "faltantes": [
          "telefono",
          "logo"
        ]
      }
    ],
    "valores": "…",
    "logo_url": null,
    "productos": []
  }
}
```
_Recortado: se omite `valores`._

### GET /proveedor/perfil/catalogos

Todas las opciones del formulario (los mismos valores que valida el servidor), las secciones con sus campos y los
límites de fotos y productos.

`HTTP 200`
```json
{
  "data": {
    "municipios": [
      "Abalá",
      "Acanceh",
      "Akil",
      "…"
    ],
    "categorias": [
      "Alimentos y Bebidas",
      "Tecnología",
      "Salud y Bienestar",
      "…"
    ],
    "tipos_oferta": [
      {
        "valor": "producto",
        "etiqueta": "Productos"
      },
      {
        "valor": "servicio",
        "etiqueta": "Servicios"
      },
      {
        "valor": "ambos",
        "etiqueta": "Ambos"
      }
    ],
    "certificaciones": [
      "Tabla Nutrimental",
      "Etiquetado de acuerdo a la NOM",
      "Código de barras",
      "Registro de marca",
      "Certificados de calidad/inocuidad"
    ],
    "coberturas_entrega": [
      {
        "valor": "local",
        "etiqueta": "Local (Mérida)"
      },
      {
        "valor": "regional",
        "etiqueta": "Estatal (Yucatán)"
      },
      {
        "valor": "nacional",
        "etiqueta": "Nacional"
      }
    ],
    "formas_entrega": [
      {
        "valor": "programada",
        "etiqueta": "Programada"
      },
      {
        "valor": "flexible",
        "etiqueta": "Flexible"
      }
    ],
    "regimenes_fiscales": [
      "Persona Física",
      "Persona Moral",
      "RESICO"
    ],
    "credito_unidades": [
      {
        "valor": "dias",
        "etiqueta": "días"
      },
      {
        "valor": "semanas",
        "etiqueta": "semanas"
      },
      {
        "valor": "meses",
        "etiqueta": "meses"
      }
    ],
    "capacidad_unidades": [
      "piezas",
      "cajas",
      "litros",
      "kilogramos"
    ],
    "secciones": [
      {
        "clave": "negocio",
        "etiqueta": "Mi negocio",
        "campos": [
          "nombre_restaurante",
          "razon_social",
          "nombre_representante",
          "curp_representante",
          "rfc",
          "num_empleados",
          "fecha_inicio_operaciones",
          "municipio",
          "categorias_json",
          "descripcion",
          "mercado_meta",
          "domicilio_en_yucatan"
        ]
      },
      "… (oferta, entrega, contacto)"
    ],
    "limites": {
      "max_productos": 5,
      "foto_max_kb": 8192,
      "foto_formatos": [
        "jpg",
        "jpeg",
        "png",
        "webp"
      ]
    }
  }
}
```
_Recortado: `municipios` trae 106 y `categorias` 22; `secciones` trae las 4 con sus campos (ver tabla)._

### PUT /proveedor/perfil/{seccion}

Límite `api-proveedor-perfil`. Body JSON con campos de la sección (ver tabla). Responde el perfil completo (como
`GET /proveedor/perfil`) + `message` (el mismo texto de la web).

`message`: `"¡Perfil completo! Fuiste aprobado automáticamente y ya apareces en el directorio del evento activo."`
(o `"…automáticamente."` si no hay evento activo) cuando el perfil queda completo, o `"Progreso guardado. Completa
todos los campos requeridos para que tu perfil se apruebe automáticamente."`.

> **Estado tras guardar:** si el perfil sigue completo, el negocio queda aprobado y activo (antes de la corrección en
> `Restaurantero::autoAprobar` quedaba desaprobado en la base pese al mensaje). Si el guardado deja el perfil
> incompleto, pierde la aprobación, igual que en la web. Mostrar siempre `data.negocio.estado`.

Wizard del proveedor nuevo — sección `negocio`:

Body: `{"descripcion": "Tortillas de maíz nixtamalizado y totopos.", "categorias_json": ["Alimentos y Bebidas"], "num_empleados": 6}`

`HTTP 200`
```json
{
  "data": {
    "negocio": {
      "id": 18,
      "nombre_negocio": "Tortillería La Nueva (demo app)",
      "estado": "pendiente",
      "motivo_rechazo": null,
      "activo": false,
      "visible_en_directorio": false,
      "perfil_completo": false,
      "porcentaje": 70,
      "logo_url": null,
      "solicitado_aprobacion_at": "2026-09-22T14:45:47-06:00"
    },
    "faltantes": [
      {
        "campo": "logo",
        "etiqueta": "Logo",
        "seccion": "contacto"
      },
      {
        "campo": "productos",
        "etiqueta": "Al menos 1 producto",
        "seccion": "oferta"
      }
    ],
    "secciones": [
      {
        "clave": "negocio",
        "completa": true,
        "faltantes": []
      },
      {
        "clave": "oferta",
        "completa": false,
        "faltantes": [
          "productos"
        ]
      },
      {
        "clave": "entrega",
        "completa": true,
        "faltantes": []
      },
      {
        "clave": "contacto",
        "completa": false,
        "faltantes": [
          "logo"
        ]
      }
    ],
    "valores": {
      "…": "…"
    }
  },
  "message": "Progreso guardado. Completa todos los campos requeridos para que tu perfil se apruebe automáticamente."
}
```
_Recortado: el resto de `data` es igual que en `GET /proveedor/perfil`._

Sección `entrega`: body `{"entrega_domicilio": true, "cobertura_entrega": "local", "forma_entrega": "programada", "pago_contraentrega": true}`

`HTTP 200`
```json
{
  "data": {
    "negocio": {
      "id": 18,
      "nombre_negocio": "Tortillería La Nueva (demo app)",
      "estado": "pendiente",
      "motivo_rechazo": null,
      "activo": false,
      "visible_en_directorio": false,
      "perfil_completo": false,
      "porcentaje": 70,
      "logo_url": null,
      "solicitado_aprobacion_at": "2026-09-22T14:45:48-06:00"
    },
    "faltantes": [
      {
        "campo": "logo",
        "etiqueta": "Logo",
        "seccion": "contacto"
      },
      {
        "campo": "productos",
        "etiqueta": "Al menos 1 producto",
        "seccion": "oferta"
      }
    ],
    "secciones": [
      {
        "clave": "negocio",
        "completa": true,
        "faltantes": []
      },
      {
        "clave": "oferta",
        "completa": false,
        "faltantes": [
          "productos"
        ]
      },
      {
        "clave": "entrega",
        "completa": true,
        "faltantes": []
      },
      {
        "clave": "contacto",
        "completa": false,
        "faltantes": [
          "logo"
        ]
      }
    ],
    "valores": {
      "…": "…"
    }
  },
  "message": "Progreso guardado. Completa todos los campos requeridos para que tu perfil se apruebe automáticamente."
}
```
_Recortado: el resto de `data` es igual que en `GET /proveedor/perfil`._

Proveedor aprobado que edita `contacto` (body `{"telefono": "9991112233", "sitio_web": "salsasdonachela.mx", "direccion": "Calle 60 x 55, Centro, Mérida"}`):
nótese `negocio.estado = pendiente` con el mensaje de aprobado (aviso de arriba) y `sitio_web` con `https://`.

`HTTP 200`
```json
{
  "data": {
    "negocio": {
      "id": 13,
      "nombre_negocio": "Salsas Doña Chela",
      "estado": "pendiente",
      "motivo_rechazo": null,
      "activo": false,
      "visible_en_directorio": false,
      "perfil_completo": true,
      "porcentaje": 100,
      "logo_url": "http://192.168.10.6/citas/public/storage/logos/demo-proveedor-prueba.png",
      "solicitado_aprobacion_at": "2026-09-22T14:45:35-06:00"
    },
    "faltantes": [],
    "secciones": "…",
    "valores": {
      "contacto": {
        "telefono": "9991112233",
        "sitio_web": "https://salsasdonachela.mx",
        "direccion": "Calle 60 x 55, Centro, Mérida"
      },
      "…": "…"
    }
  },
  "message": "¡Perfil completo! Fuiste aprobado automáticamente y ya apareces en el directorio del evento activo."
}
```
_Recortado: el resto de `data` es igual que en `GET /proveedor/perfil`._

422 — `entrega_domicilio = true` sin cobertura ni forma:

`HTTP 422`
```json
{
  "message": "El campo cobertura entrega es obligatorio.",
  "errors": {
    "cobertura_entrega": [
      "El campo cobertura entrega es obligatorio."
    ]
  }
}
```

### PUT /proveedor/perfil

Igual que por sección, pero acepta campos de todas. Body de ejemplo con errores:
`{"nombre_restaurante": "", "num_empleados": -3, "sitio_web": "xxx…(201 caracteres)"}`

`HTTP 422`
```json
{
  "message": "El campo nombre restaurante es obligatorio. (and 2 more errors)",
  "errors": {
    "nombre_restaurante": [
      "El campo nombre restaurante es obligatorio."
    ],
    "num_empleados": [
      "El campo num empleados debe ser al menos 0."
    ],
    "sitio_web": [
      "El campo sitio web no debe ser mayor que 200 caracteres."
    ]
  }
}
```

### POST /proveedor/logo

`multipart/form-data` con el campo `foto` (obligatorio en la API). Mismas reglas que la web: `image`,
`jpg/jpeg/png/webp`, máx. **8 MB** (8192 KB). Reemplaza y borra el logo anterior. Responde el perfil + `message`.
Con el logo, el proveedor nuevo del wizard completa el perfil y se auto-aprueba (correo + registro PENDIENTE al
encuentro activo, que decide el admin):

`HTTP 200`
```json
{
  "data": {
    "negocio": {
      "id": 18,
      "nombre_negocio": "Tortillería La Nueva (demo app)",
      "estado": "aprobado",
      "motivo_rechazo": null,
      "activo": true,
      "visible_en_directorio": true,
      "perfil_completo": true,
      "porcentaje": 100,
      "logo_url": "http://192.168.10.6/citas/public/storage/proveedores/fotos/SaHlmzalYOB5YRznZxgzNukEr7misq66c75lzB7V.png",
      "solicitado_aprobacion_at": "2026-09-22T14:45:50-06:00"
    },
    "faltantes": [],
    "secciones": "…",
    "valores": {
      "…": "…"
    },
    "logo_url": "http://192.168.10.6/citas/public/storage/proveedores/fotos/SaHlmzalYOB5YRznZxgzNukEr7misq66c75lzB7V.png",
    "productos": [
      {
        "indice": 0,
        "nombre": "Tortilla de maíz 1 kg",
        "descripcion": null,
        "foto_url": "http://192.168.10.6/citas/public/storage/proveedores/productos/vBjO9jYNiwckrccIwYvyxH3AcUmcDF5ZxBlT6tn7.png",
        "capacidad_cantidad": 800,
        "capacidad_unidad": "kilogramos"
      }
    ]
  },
  "message": "¡Perfil completo! Fuiste aprobado automáticamente y ya apareces en el directorio del evento activo."
}
```
_Recortado: el resto de `data` es igual que en `GET /proveedor/perfil`._

`HTTP 422`
```json
{
  "message": "El campo foto debe ser una imagen. (and 1 more error)",
  "errors": {
    "foto": [
      "El campo foto debe ser una imagen.",
      "El campo foto debe ser un archivo con extensión: jpg, jpeg, png, webp."
    ]
  }
}
```

---

### Productos

Hasta **5** productos (`productos_top`). Se identifican por su posición `indice` (0–4), igual que en la web. Cada alta,
edición o baja guarda el perfil completo con las reglas de la web (así que también re-dispara la aprobación, ver aviso).

| Campo | Tipo | Reglas |
|---|---|---|
| `nombre` | string | **obligatorio en la API** (la web descarta en silencio una fila sin nombre), `max:200` |
| `descripcion` | string\|null | `max:500` |
| `capacidad_cantidad` | number\|null | capacidad de producción mensual, `numeric, min:0` |
| `capacidad_unidad` | string\|null | `piezas`, `cajas`, `litros`, `kilogramos` |
| `foto` | archivo | `image, mimes:jpg,jpeg,png,webp, max:8192` (KB). Solo con `multipart/form-data` |

Los errores usan los nombres de arriba (`nombre`, `capacidad_unidad`, `foto`); el tope de 5 responde en `productos`.

#### GET /proveedor/productos

`HTTP 200`
```json
{
  "data": [
    {
      "indice": 0,
      "nombre": "Recado rojo 1 kg",
      "descripcion": "Achiote artesanal",
      "foto_url": null,
      "capacidad_cantidad": 500,
      "capacidad_unidad": "kilogramos"
    },
    {
      "indice": 1,
      "nombre": "Salsa de habanero 250 ml",
      "descripcion": "Picor alto",
      "foto_url": null,
      "capacidad_cantidad": 2000,
      "capacidad_unidad": "piezas"
    }
  ],
  "meta": {
    "max_productos": 5,
    "restantes": 3,
    "foto_max_kb": 8192,
    "foto_formatos": [
      "jpg",
      "jpeg",
      "png",
      "webp"
    ],
    "capacidad_unidades": [
      "piezas",
      "cajas",
      "litros",
      "kilogramos"
    ]
  }
}
```

#### POST /proveedor/productos

JSON o `multipart/form-data` (si lleva `foto`). **201** con el producto creado, el `negocio` y el `message` del guardado.

Body (multipart): `nombre=Recado negro 500 g`, `descripcion=Para relleno negro`, `capacidad_cantidad=300`,
`capacidad_unidad=piezas`, `foto=@recado.png`

`HTTP 201`
```json
{
  "data": {
    "indice": 2,
    "nombre": "Recado negro 500 g",
    "descripcion": "Para relleno negro",
    "foto_url": "http://192.168.10.6/citas/public/storage/proveedores/productos/urY8jd4M5km8AH10RZ1qz6NfaYO6buUoiLST8Spu.png",
    "capacidad_cantidad": 300,
    "capacidad_unidad": "piezas"
  },
  "negocio": {
    "id": 13,
    "nombre_negocio": "Salsas Doña Chela",
    "estado": "aprobado",
    "motivo_rechazo": null,
    "activo": true,
    "visible_en_directorio": true,
    "perfil_completo": true,
    "porcentaje": 100,
    "logo_url": "http://192.168.10.6/citas/public/storage/logos/demo-proveedor-prueba.png",
    "solicitado_aprobacion_at": "2026-09-22T14:45:36-06:00"
  },
  "message": "¡Perfil completo! Fuiste aprobado automáticamente y ya apareces en el directorio del evento activo."
}
```

`HTTP 422`
```json
{
  "message": "El nombre del producto es obligatorio.",
  "errors": {
    "nombre": [
      "El nombre del producto es obligatorio."
    ]
  }
}
```

Con 5 productos: `422 {"errors": {"productos": ["El campo productos no debe tener más de 5 elementos."]}}`.

#### PUT /proveedor/productos/{indice} (o POST para multipart)

Edición parcial: solo cambian los campos enviados (el resto y la foto se conservan). Para cambiar la foto usa **POST**
con `multipart/form-data` (PHP no lee archivos en un PUT). `PATCH` también se acepta.

Body `{"capacidad_cantidad": 450}`:

`HTTP 200`
```json
{
  "data": {
    "indice": 2,
    "nombre": "Recado negro 500 g",
    "descripcion": "Para relleno negro",
    "foto_url": "http://192.168.10.6/citas/public/storage/proveedores/productos/urY8jd4M5km8AH10RZ1qz6NfaYO6buUoiLST8Spu.png",
    "capacidad_cantidad": 450,
    "capacidad_unidad": "piezas"
  },
  "negocio": {
    "id": 13,
    "nombre_negocio": "Salsas Doña Chela",
    "estado": "pendiente",
    "motivo_rechazo": null,
    "activo": false,
    "visible_en_directorio": false,
    "perfil_completo": true,
    "porcentaje": 100,
    "logo_url": "http://192.168.10.6/citas/public/storage/logos/demo-proveedor-prueba.png",
    "solicitado_aprobacion_at": "2026-09-22T14:45:37-06:00"
  },
  "message": "¡Perfil completo! Fuiste aprobado automáticamente y ya apareces en el directorio del evento activo."
}
```

Foto con formato no permitido:

`HTTP 422`
```json
{
  "message": "El campo foto debe ser una imagen. (and 1 more error)",
  "errors": {
    "foto": [
      "El campo foto debe ser una imagen.",
      "El campo foto debe ser un archivo con extensión: jpg, jpeg, png, webp."
    ]
  }
}
```

`HTTP 404`
```json
{
  "message": "Producto no encontrado."
}
```

#### DELETE /proveedor/productos/{indice}

Quita el producto; los que siguen suben una posición **conservando cada uno su foto** (la foto del borrado se elimina
del disco). Responde la lista como `GET /proveedor/productos` + `message`.

`HTTP 200`
```json
{
  "data": [
    {
      "indice": 0,
      "nombre": "Recado rojo 1 kg",
      "descripcion": "Achiote artesanal",
      "foto_url": null,
      "capacidad_cantidad": 500,
      "capacidad_unidad": "kilogramos"
    },
    {
      "indice": 1,
      "nombre": "Salsa de habanero 250 ml",
      "descripcion": "Picor alto",
      "foto_url": null,
      "capacidad_cantidad": 2000,
      "capacidad_unidad": "piezas"
    }
  ],
  "meta": {
    "max_productos": 5,
    "restantes": 3,
    "foto_max_kb": 8192,
    "foto_formatos": [
      "jpg",
      "jpeg",
      "png",
      "webp"
    ],
    "capacidad_unidades": [
      "piezas",
      "cajas",
      "litros",
      "kilogramos"
    ]
  },
  "message": "¡Perfil completo! Fuiste aprobado automáticamente y ya apareces en el directorio del evento activo."
}
```

---

### GET /proveedor/citas

Citas recibidas por pestaña, como `/proveedor/citas` de la web. Query: `tab` ∈ `pendientes` (default), `confirmadas`
(`confirmada`, `pendiente_reconfirmacion`, `reagendada`), `rechazadas` (`rechazada`, `cancelada`), `todas`;
`per_page` 1–50 (default 20). Orden: `inicio` descendente. `meta.contadores` = los números de las pestañas.
Si la cuenta aún no tiene negocio responde `data: []` con `meta.motivo` (la web da 403).

`?tab=confirmadas` (la primera es una cita con propuesta de reagenda esperando al comprador):

`HTTP 200`
```json
{
  "data": [
    {
      "id": 50,
      "inicio": "2026-10-06T13:30:00-06:00",
      "fin": "2026-10-06T14:00:00-06:00",
      "estado": "pendiente_reconfirmacion",
      "estado_texto": "Nuevo horario por confirmar",
      "notas": "Cita de demostración para la app.",
      "productos_interes": [],
      "mesa": null,
      "evento": {
        "id": 5,
        "nombre": "Encuentro de Negocios Impúlsate (demo app)"
      },
      "comprador": {
        "id": 43,
        "nombre": "Compras Restaurante Chaya Maya",
        "email": "demo.comprador5@prueba.impulsate.mx",
        "telefono": "9992220005",
        "nombre_empresa": "Restaurante Chaya Maya",
        "necesidades": "Recado rojo y negro para cochinita y relleno negro."
      },
      "propuesta_reagenda": {
        "inicio": "2026-10-06T15:30:00-06:00",
        "fin": "2026-10-06T16:00:00-06:00"
      },
      "puede_aceptar": false,
      "puede_rechazar": false,
      "puede_reagendar": false,
      "creada_at": "2026-09-22T14:42:51-06:00"
    },
    {
      "id": 48,
      "inicio": "2026-10-06T12:00:00-06:00",
      "fin": "2026-10-06T12:30:00-06:00",
      "estado": "confirmada",
      "estado_texto": "Confirmada",
      "notas": "Cita de demostración para la app.",
      "productos_interes": [],
      "mesa": "Mesa 5",
      "evento": {
        "id": 5,
        "nombre": "Encuentro de Negocios Impúlsate (demo app)"
      },
      "comprador": {
        "id": 41,
        "nombre": "Compras Cocina Económica Doña Mary",
        "email": "demo.comprador3@prueba.impulsate.mx",
        "telefono": "9992220003",
        "nombre_empresa": "Cocina Económica Doña Mary",
        "necesidades": "Salsas picantes por mayoreo."
      },
      "propuesta_reagenda": null,
      "puede_aceptar": false,
      "puede_rechazar": false,
      "puede_reagendar": true,
      "creada_at": "2026-09-22T14:42:51-06:00"
    }
  ],
  "links": {
    "first": "http://192.168.10.6/citas/public/api/v1/proveedor/citas?tab=confirmadas&page=1",
    "last": "http://192.168.10.6/citas/public/api/v1/proveedor/citas?tab=confirmadas&page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "links": "…",
    "path": "http://192.168.10.6/citas/public/api/v1/proveedor/citas",
    "per_page": 20,
    "to": 3,
    "total": 3,
    "tab": "confirmadas",
    "contadores": {
      "pendientes": 2,
      "confirmadas": 3,
      "rechazadas": 1
    }
  }
}
```
_Recortado: `data` traía 3 elementos; se muestran 2; se omite `meta.links`._

`HTTP 422`
```json
{
  "message": "El campo tab seleccionado no es válido.",
  "errors": {
    "tab": [
      "El campo tab seleccionado no es válido."
    ]
  }
}
```

### GET /proveedor/citas/{id}

`HTTP 200`
```json
{
  "data": {
    "id": 47,
    "inicio": "2026-10-06T11:30:00-06:00",
    "fin": "2026-10-06T12:00:00-06:00",
    "estado": "pendiente",
    "estado_texto": "Pendiente",
    "notas": "Cita de demostración para la app.",
    "productos_interes": [],
    "mesa": null,
    "evento": {
      "id": 5,
      "nombre": "Encuentro de Negocios Impúlsate (demo app)"
    },
    "comprador": {
      "id": 40,
      "nombre": "Compras Hotel Hacienda Xcanatún",
      "email": "demo.comprador2@prueba.impulsate.mx",
      "telefono": "9992220002",
      "nombre_empresa": "Hotel Hacienda Xcanatún",
      "necesidades": "Recados y salsas para el restaurante del hotel."
    },
    "propuesta_reagenda": null,
    "puede_aceptar": true,
    "puede_rechazar": true,
    "puede_reagendar": true,
    "creada_at": "2026-09-22T14:42:51-06:00"
  }
}
```

### GET /proveedor/citas/{id}/disponibilidad

Horarios que el proveedor puede proponer al reagendar esta cita, con **las mismas reglas que
`POST …/reagendar`**: la rejilla del evento (paso = `tiempo_entre_citas_minutos`), la cita no choca consigo misma, el
proveedor no lleva colchón y el comprador sí (10 min). Query: `fecha` o `desde`/`hasta` (`YYYY-MM-DD`).

`horarios[].estado`: `libre`, `ocupado_proveedor` (otra cita tuya), `ocupado_comprador` (otra cita del comprador,
con su colchón) o `pasado`. `es_horario_actual` marca el horario que la cita tiene hoy.

`HTTP 200`
```json
{
  "data": {
    "cita_id": 47,
    "puede_reagendar": true,
    "evento": {
      "id": 5,
      "nombre": "Encuentro de Negocios Impúlsate (demo app)"
    },
    "duracion_cita_minutos": 30,
    "intervalo_minutos": 30,
    "colchon_comprador_minutos": 10,
    "horario_actual": {
      "inicio": "2026-10-06T11:30:00-06:00",
      "fin": "2026-10-06T12:00:00-06:00"
    },
    "dias": [
      {
        "fecha": "2026-10-06",
        "horarios": [
          {
            "hora": "09:00",
            "inicio": "2026-10-06T09:00:00-06:00",
            "fin": "2026-10-06T09:30:00-06:00",
            "estado": "libre",
            "es_horario_actual": false
          },
          {
            "hora": "09:30",
            "inicio": "2026-10-06T09:30:00-06:00",
            "fin": "2026-10-06T10:00:00-06:00",
            "estado": "libre",
            "es_horario_actual": false
          },
          {
            "hora": "10:00",
            "inicio": "2026-10-06T10:00:00-06:00",
            "fin": "2026-10-06T10:30:00-06:00",
            "estado": "ocupado_proveedor",
            "es_horario_actual": false
          },
          {
            "hora": "10:30",
            "inicio": "2026-10-06T10:30:00-06:00",
            "fin": "2026-10-06T11:00:00-06:00",
            "estado": "libre",
            "es_horario_actual": false
          },
          {
            "hora": "11:00",
            "inicio": "2026-10-06T11:00:00-06:00",
            "fin": "2026-10-06T11:30:00-06:00",
            "estado": "ocupado_proveedor",
            "es_horario_actual": false
          },
          {
            "hora": "11:30",
            "inicio": "2026-10-06T11:30:00-06:00",
            "fin": "2026-10-06T12:00:00-06:00",
            "estado": "libre",
            "es_horario_actual": true
          }
        ]
      }
    ]
  }
}
```
_Recortado: el día trae 18 horarios (09:00 a 17:30); se muestran los primeros 6._

### POST /proveedor/citas/{id}/aceptar

Sin body. Límite `api-proveedor-citas`. Igual que la web: la cita pasa a `confirmada`, el comprador recibe el correo
`CitaAceptada` y la notificación "Cita confirmada". Solo citas `pendiente` (si no, 422 — la web no lo comprueba).

`HTTP 200`
```json
{
  "data": {
    "id": 47,
    "inicio": "2026-10-06T11:30:00-06:00",
    "fin": "2026-10-06T12:00:00-06:00",
    "estado": "confirmada",
    "estado_texto": "Confirmada",
    "notas": "Cita de demostración para la app.",
    "productos_interes": [],
    "mesa": null,
    "evento": {
      "id": 5,
      "nombre": "Encuentro de Negocios Impúlsate (demo app)"
    },
    "comprador": {
      "id": 40,
      "nombre": "Compras Hotel Hacienda Xcanatún",
      "email": "demo.comprador2@prueba.impulsate.mx",
      "telefono": "9992220002",
      "nombre_empresa": "Hotel Hacienda Xcanatún",
      "necesidades": "Recados y salsas para el restaurante del hotel."
    },
    "propuesta_reagenda": null,
    "puede_aceptar": false,
    "puede_rechazar": false,
    "puede_reagendar": true,
    "creada_at": "2026-09-22T14:42:51-06:00"
  },
  "message": "Cita aceptada. El comprador fue notificado."
}
```

`HTTP 422`
```json
{
  "message": "Solo puedes aceptar citas pendientes.",
  "errors": {
    "estado": [
      "Solo puedes aceptar citas pendientes."
    ]
  }
}
```

### POST /proveedor/citas/{id}/rechazar

Sin body. Pasa a `rechazada`, correo `CitaRechazada` y notificación "Cita rechazada" al comprador. Solo `pendiente`.

`HTTP 200`
```json
{
  "data": {
    "id": 47,
    "inicio": "2026-10-06T11:30:00-06:00",
    "fin": "2026-10-06T12:00:00-06:00",
    "estado": "rechazada",
    "estado_texto": "Rechazada",
    "notas": "Cita de demostración para la app.",
    "productos_interes": [],
    "mesa": null,
    "evento": {
      "id": 5,
      "nombre": "Encuentro de Negocios Impúlsate (demo app)"
    },
    "comprador": {
      "id": 40,
      "nombre": "Compras Hotel Hacienda Xcanatún",
      "email": "demo.comprador2@prueba.impulsate.mx",
      "telefono": "9992220002",
      "nombre_empresa": "Hotel Hacienda Xcanatún",
      "necesidades": "Recados y salsas para el restaurante del hotel."
    },
    "propuesta_reagenda": null,
    "puede_aceptar": false,
    "puede_rechazar": false,
    "puede_reagendar": false,
    "creada_at": "2026-09-22T14:42:51-06:00"
  },
  "message": "Cita rechazada. El comprador fue notificado."
}
```

### POST /proveedor/citas/{id}/reagendar

Propone otro horario. Body JSON (mismas reglas que la web):

| Campo | Reglas |
|---|---|
| `propuesta_fecha` | requerido, fecha, hoy o después |
| `propuesta_hora` | requerido, `HH:MM` |

La cita queda en `pendiente_reconfirmacion` con `propuesta_reagenda`; conserva su horario hasta que el comprador
acepte (`POST /citas/{id}/reagenda/aceptar` o el enlace del correo). El comprador recibe el correo `CitaReagendada` y
la notificación "Propuesta de cambio de horario". Solo para `pendiente`, `confirmada` o `reagendada` (422 si no).

Body `{"propuesta_fecha": "2026-10-06", "propuesta_hora": "14:30"}`:

`HTTP 200`
```json
{
  "data": {
    "id": 31,
    "inicio": "2026-10-06T11:00:00-06:00",
    "fin": "2026-10-06T11:30:00-06:00",
    "estado": "pendiente_reconfirmacion",
    "estado_texto": "Nuevo horario por confirmar",
    "notas": "Cita de demostración para la app.",
    "productos_interes": [],
    "mesa": null,
    "evento": {
      "id": 5,
      "nombre": "Encuentro de Negocios Impúlsate (demo app)"
    },
    "comprador": {
      "id": 24,
      "nombre": "Restaurante La Tradición",
      "email": "demo.comprador1@prueba.impulsate.mx",
      "telefono": "9992223344",
      "nombre_empresa": "Restaurante La Tradición",
      "necesidades": "Salsas, recados y miel para el menú de temporada."
    },
    "propuesta_reagenda": {
      "inicio": "2026-10-06T14:30:00-06:00",
      "fin": "2026-10-06T15:00:00-06:00"
    },
    "puede_aceptar": false,
    "puede_rechazar": false,
    "puede_reagendar": false,
    "creada_at": "2026-09-22T13:35:33-06:00"
  },
  "message": "Propuesta de reagendamiento enviada al comprador."
}
```

Hueco ocupado (el proveedor ya tiene cita a esa hora; si la tiene el comprador el mensaje es "Ya tienes una cita
agendada a las HH:MM (se requieren 10 minutos de separación entre citas)."):

`HTTP 422`
```json
{
  "message": "Este horario ya no está disponible con el proveedor. Por favor elige otro.",
  "errors": {
    "fecha": [
      "Este horario ya no está disponible con el proveedor. Por favor elige otro."
    ]
  }
}
```

`HTTP 422`
```json
{
  "message": "El campo propuesta fecha debe ser una fecha posterior o igual a today. (and 1 more error)",
  "errors": {
    "propuesta_fecha": [
      "El campo propuesta fecha debe ser una fecha posterior o igual a today."
    ],
    "propuesta_hora": [
      "El campo propuesta hora no corresponde con el formato H:i."
    ]
  }
}
```

`HTTP 403`
```json
{
  "message": "This action is unauthorized."
}
```

---

### Registro del proveedor a eventos

Se usa el endpoint existente `POST /eventos/{id}/registro` con `{"tipo": "proveedor"}` (encuentro) o
`{"tipo": "expositor"}` (bazar). Cambios de la Fase 3:

- Una cuenta con los dos roles puede registrarse como proveedor sin cambiar `rol_activo`, y la respuesta ya viene
  calculada como proveedor (`tipo_registro = proveedor`).
- `GET /eventos` y `GET /eventos/{id}` aceptan `?vista=proveedor` para ver `mi_registro`/`accion` como proveedor.
- `mi_registro.expositor` trae además `seleccionado` (bool): en el bazar la web distingue "aprobado y con lugar" de
  "aprobado sin lugar".
- Los requisitos del bazar (INE + CSF vigente) llegan en `motivo_bloqueo` del evento y en `requisitos_bazar` del panel.

Proveedor recién completado (el perfil completo ya lo inscribió PENDIENTE al encuentro activo, por eso `ya_pendiente`):

`HTTP 200`
```json
{
  "data": {
    "id": 5,
    "nombre": "Encuentro de Negocios Impúlsate (demo app)",
    "descripcion": "Encuentro de negocios entre compradores del sector restaurantero y proveedores locales de Yucatán.",
    "sector_economico": "Alimentos y Bebidas",
    "tipo_evento": "encuentro_negocios",
    "imagen_url": null,
    "convocatoria_url": null,
    "fecha_hora_inicio": "2026-10-06T09:00:00-06:00",
    "fecha_hora_fin": "2026-10-06T18:00:00-06:00",
    "estado": "activo",
    "destacado": true,
    "fases": "…",
    "mi_registro": {
      "comprador": null,
      "proveedor": {
        "estado": "pendiente",
        "motivo_rechazo": null,
        "registrado_at": "2026-09-22T14:45:50-06:00"
      },
      "expositor": null
    },
    "tipo_registro": "proveedor",
    "puede_registrarse": false,
    "motivo_bloqueo": null,
    "accion": "en_revision"
  },
  "message": "Tu solicitud ya está en revisión. Te avisaremos cuando sea aprobada.",
  "resultado": "ya_pendiente"
}
```
_Recortado: `fases` como en `GET /eventos/{id}`._

Bazar sin INE:

`HTTP 422`
```json
{
  "message": "Debes subir tu INE antes de registrarte al bazar.",
  "errors": {
    "error": [
      "Debes subir tu INE antes de registrarte al bazar."
    ]
  }
}
```

### Cambios en GET /inicio

Se agrega `data.proveedor` (sin tocar los campos existentes): `null` si la cuenta no tiene rol de proveedor; si lo
tiene (aunque esté en modo comprador):

| Campo | Contenido |
|---|---|
| `negocio` | objeto `negocio` |
| `perfil_completo` | bool |
| `secciones_faltantes` | string[] — secciones del wizard con algo pendiente |
| `citas_por_responder` | citas `pendiente` |
| `reagendas_esperando_comprador` | citas en `pendiente_reconfirmacion` |
| `citas_hoy` | int |

`HTTP 200`
```json
{
  "data": {
    "usuario": "…",
    "onboarding": "…",
    "vista": "…",
    "proxima_cita": "…",
    "citas_proximas_count": "…",
    "evento_destacado": "…",
    "notificaciones_sin_leer": "…",
    "publicidad": "…",
    "pendientes": "…",
    "proveedor": {
      "negocio": {
        "id": 13,
        "nombre_negocio": "Salsas Doña Chela",
        "estado": "aprobado",
        "motivo_rechazo": null,
        "activo": true,
        "visible_en_directorio": true,
        "perfil_completo": true,
        "porcentaje": 100,
        "logo_url": "http://192.168.10.6/citas/public/storage/logos/demo-proveedor-prueba.png",
        "solicitado_aprobacion_at": "2026-09-22T14:43:52-06:00"
      },
      "perfil_completo": true,
      "secciones_faltantes": [],
      "citas_por_responder": 2,
      "reagendas_esperando_comprador": 1,
      "citas_hoy": 0
    }
  }
}
```
_Recortado: los demás campos no cambian (ver `GET /inicio`)._

---

## 13. Datos de prueba locales

`php artisan impulsate:demo-movil` (solo `APP_ENV=local`, idempotente) deja:

| Cuenta | Contraseña | Qué tiene |
|---|---|---|
| `comprador@prueba.impulsate.mx` | `Prueba2026!` | comprador aprobado en el encuentro; 5 citas (confirmada, pendiente, cancelada, una en `pendiente_reconfirmacion` con propuesta 13:00 → 14:00 y una completada en el evento finalizado); 1 agenda propuesta pendiente con 3 citas (15:00, 15:30 y 16:00); 1 encuesta de satisfacción por responder (evento finalizado, plantilla activa); 5 notificaciones (3 sin leer) |
| `proveedor@prueba.impulsate.mx` | `Proveedor2026!` | proveedor "Salsas Doña Chela" con perfil de negocio completo, aprobado en el encuentro, 2 productos con capacidad mensual; 6 citas recibidas: 2 pendientes por aceptar (11:00 y 11:30), 2 confirmadas (10:00 Mesa 3 y 12:00 Mesa 5), 1 rechazada (12:30) y 1 con propuesta de reagenda esperando al comprador (13:30 → 15:30) |
| `proveedor.nuevo@prueba.impulsate.mx` | `Proveedor2026!` | proveedor "Tortillería La Nueva (demo app)" con perfil de negocio INCOMPLETO (faltan descripción, teléfono, logo, productos y logística), sin registro a eventos: sirve para el wizard |
| `admin@prueba.impulsate.mx` | (la existente) | rol admin |
| `demo.proveedor1..8@prueba.impulsate.mx` | `Proveedor2026!` | 8 proveedores aprobados en el encuentro (sin logo) |
| `demo.comprador1@prueba.impulsate.mx` | `Prueba2026!` | comprador con una cita pendiente con el proveedor de prueba y `necesidades` capturadas (sirve para `GET /proveedores?orden=coincidencia`) |
| `demo.comprador2..5@prueba.impulsate.mx` | `Prueba2026!` | compradores aprobados en el encuentro con una cita cada uno con el proveedor de prueba (pendiente, confirmada, rechazada y la de la reagenda) |

Eventos: "Encuentro de Negocios Impúlsate (demo app)" (activo, destacado, en 2 semanas, ventanas abiertas),
"Bazar Hecho en Yucatán (demo app)" (activo) y "Encuentro de Negocios Julio (demo app)" (finalizado).

Correr el comando otra vez **reinicia** la reagenda, la agenda propuesta (borra las citas que haya creado una
aceptación anterior) y la encuesta (borra sus respuestas), así que las pantallas de Fase 2 se pueden probar las veces
que se quiera. Las citas nuevas que se agenden desde la app no se borran.

También **reinicia** las 6 citas del proveedor de prueba (estado y horario), lo vuelve a dejar aprobado con su perfil
completo, y deja otra vez INCOMPLETO y sin registros a eventos a `proveedor.nuevo@…`, así que el wizard y las
respuestas a citas (Fase 3) se pueden repetir.
