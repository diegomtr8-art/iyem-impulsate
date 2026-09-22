# API v1 — Impúlsate (app móvil)

Contrato de la API que consume la app Expo. **Todos los ejemplos de respuesta son reales**: se copiaron de llamadas
hechas el 2026-09-22 contra la BD local (`php artisan impulsate:demo-movil`). Donde un ejemplo se recortó
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
  (`perfil_proveedor_completo`), que se edita en la Fase 3; mientras tanto la app puede abrir la web
  (`/proveedor/completar-perfil`).

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

## 8. Datos de prueba locales

`php artisan impulsate:demo-movil` (solo `APP_ENV=local`, idempotente) deja:

| Cuenta | Contraseña | Qué tiene |
|---|---|---|
| `comprador@prueba.impulsate.mx` | `Prueba2026!` | comprador aprobado en el encuentro; 4 citas (confirmada, pendiente, cancelada y una completada en el evento finalizado); 5 notificaciones (3 sin leer) |
| `proveedor@prueba.impulsate.mx` | `Proveedor2026!` | proveedor "Salsas Doña Chela" con perfil de negocio completo, aprobado en el encuentro, 2 citas recibidas |
| `admin@prueba.impulsate.mx` | (la existente) | rol admin |
| `demo.proveedor1..8@prueba.impulsate.mx` | `Proveedor2026!` | 8 proveedores aprobados en el encuentro (sin logo) |
| `demo.comprador1@prueba.impulsate.mx` | `Prueba2026!` | comprador con una cita pendiente con el proveedor de prueba |

Eventos: "Encuentro de Negocios Impúlsate (demo app)" (activo, destacado, en 2 semanas, ventanas abiertas),
"Bazar Hecho en Yucatán (demo app)" (activo) y "Encuentro de Negocios Julio (demo app)" (finalizado).
