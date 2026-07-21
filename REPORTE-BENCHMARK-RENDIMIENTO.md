# Reporte de Benchmark de Rendimiento — vc-flashcards + vc-onboarding-wizard

Fecha: 2026-07-21
Alcance: experiencia real de usuario (percepción de velocidad) en Study Sessions, Flashcards Library y Mock Test, incluyendo el shell del dashboard de `vc-onboarding-wizard` que los hospeda. Este documento **no** es una auditoría de calidad de código — esas ya existen en `AUDITORIA-RENDIMIENTO.md` de cada plugin. Este es un benchmark narrado por escenario de uso.

---

## 1. Nota metodológica (leer antes que nada)

**No se usó ningún profiler real.** No hay una instancia de WordPress corriendo, ni Query Monitor, ni Chrome DevTools Performance, ni WebPageTest. Todos los tiempos de este documento son **estimaciones modeladas**, no mediciones, construidas a partir de:

- Conteo de queries y `WP_Query`/`$wpdb` por acción, leyendo el código línea por línea.
- Verificación de si cada consulta hoy es cache-hit o cache-miss contra los transients ya implementados (ver sección de estado de fixes abajo).
- Pesos reales de archivos en disco (`ls -la` sobre `assets/*.js`, `assets/*.css`).
- Si cada script/estilo bloquea el `<head>` o va diferido a `wp_footer` (revisando el último argumento de `wp_enqueue_script()`).
- Duraciones de `transition`/`animation`/`@keyframes` leídas directamente de las hojas CSS.
- Supuestos de hosting compartido típico: PHP-FPM + MySQL en el mismo host, sin Redis/Memcached, sin CDN. Rangos usados: ~1-3ms por query indexada simple, ~15-40ms por un `get_posts(-1)` con bucle `get_the_terms()` sobre cientos de filas, ~80-150ms de latencia típica por round-trip AJAX (red + PHP + vuelta).
- Catálogo de referencia: ~240-340 flashcards, tomado de `test-data-240.csv`/`test-data-340.csv` (confirmado con `wc -l`), como proxy del tamaño real de catálogo de este proyecto.

**Recomendación:** validar estas cifras con Query Monitor, Chrome DevTools Performance o WebPageTest antes de tomar cualquier número como exacto. El valor de este documento está en identificar **dónde** y **por qué** ocurre cada demora, no en las cifras absolutas.

### Estado de fixes ya aplicado (no se reportan como problemas abiertos)

**`vc-flashcards`:** `get_study_sessions_categories()` / `get_flashcards_library_categories()` ya usan una base cacheada (transient, TTL 600s, versión lógica) más un overlay barato por usuario; el widget `help-fab` ya está condicionado a páginas con shortcode; `get_flashcard_ids_for_term()` ya tiene `no_found_rows`. Confirmado con `ls`: `assets/general.css` **sigue sin existir** (404 real, aún no corregido).

**`vc-onboarding-wizard`:** `get_profile_countries()` / `get_profile_states()` ya están cacheadas vía transient; `onboarding-left.webp` (18.3 KB) ya reemplazó al `.png` de 897 KB.

---

## 2. Matriz de pruebas

### Cold Load (primera entrada, cache miss)

| Aspecto | Observación |
|---|---|
| Tiempo hasta interfaz | ~450-700ms totales (Study Sessions es el más pesado de los tres módulos en este escenario) |
| TTFB estimado | ~120-220ms — `get_study_sessions_categories_base()` en cache miss recorre `get_terms()` + `get_posts(-1)` de ~240-340 tarjetas + `get_the_terms()` por tarjeta |
| Recursos que cargan | `flashcards.css` (121.8 KB), `flashcards.js` (62.8 KB, diferido en footer, no bloqueante), fuente Google Fonts vía `<link>` en `<head>` (bloqueante) |
| Qué bloquea la pantalla | Solo el CSS del `<head>` y la fuente externa; el JS va en footer y no bloquea el primer render |
| Pantalla vacía | No — todo el HTML llega ya calculado desde el servidor |
| Contenido anterior (stale) | No aplica, no hay stale-while-revalidate en este flujo |
| Loaders tardíos | No se detectaron |

**Veredicto:** cold start normal de un transient con TTL de 600s. No es un problema real, es el costo esperado de la primera lectura tras expirar la caché.

### Warm Load (segunda entrada, dentro de la ventana de 600s)

| Aspecto | Observación |
|---|---|
| Tiempo hasta interfaz | ~150-280ms |
| Qué mejora | `get_transient()` (~1-3ms) reemplaza el recorrido completo de términos/posts; solo corre el overlay por usuario (`get_mastered_flashcard_ids()`, indexado por `user_id`) |
| Qué sigue cargando igual | La petición 404 a `assets/general.css` persiste igual — no depende de la caché de datos, es un recurso enqueued que nunca existió |
| Carga innecesaria repetida | Ninguna detectada en el flujo de datos; el overlay por usuario es correcto por diseño (debe reflejar el progreso más reciente) |

**Veredicto:** comportamiento correcto, la caché está funcionando como se espera.

---

## 3. Medición por flujo

### Flashcards Library

| Acción | Tiempo estimado | Problema encontrado | Causa | Severidad |
|---|---|---|---|---|
| Abrir módulo | 150-250ms | Petición 404 a `assets/general.css` en paralelo | Descarga de recursos (recurso roto) | Baja |
| Elegir categoría e iniciar sesión | 90-170ms | Ninguno | Consulta WordPress/AJAX (`ajax_start_session`: transacción + `SELECT FOR UPDATE` + `INSERT` de sesión/snapshot; el lock es por diseño, no es un problema) | N/A |
| Cargar primera tarjeta | 0ms | Ninguno | Ya viene incluida en el payload inicial de la sesión | N/A |
| Cambiar tarjeta | 5-15ms JS + 150-200ms transición visual | Ninguno | Procesamiento JavaScript + animación/transición CSS | N/A |
| Terminar sesión | 90-170ms | Ninguno | Consulta WordPress/AJAX (`ajax_complete_session`, bucle de `INSERT` acotado a 10-20 filas) | N/A |

### Study Sessions

| Acción | Tiempo estimado | Problema encontrado | Causa | Severidad |
|---|---|---|---|---|
| Abrir módulo (cold) | 250-400ms | El más costoso de los tres módulos en frío, por las métricas ACS agregadas | Consultas WordPress (agregación de categorías) | Baja (ya cacheado tras H-01) |
| Abrir módulo (warm) | 120-200ms | Payload `data-categories` embebido en atributo HTML, doble-escapado por `esc_attr()` sobre JSON | Renderizado de componentes (parseo de JSON en atributo) | Baja hoy, escala con el catálogo |
| Elegir categoría/cantidad | ~instantáneo | Ninguno | Interacción local, sin red | N/A |
| Iniciar sesión | 90-170ms | Ninguno | Consulta WordPress/AJAX (`ajax_start_session`) | N/A |
| Cargar primera tarjeta | 0ms | Ninguno | Ya incluida en el payload | N/A |
| Cambiar tarjeta | 5-10ms JS | Ninguno | Procesamiento JavaScript (sin flip 3D, no hay keyframes de flip en `flashcards.css`) | N/A |
| Terminar sesión | 90-170ms | Ninguno | Consulta WordPress/AJAX (`ajax_complete_session`) | N/A |

### Mock Test

| Acción | Tiempo estimado | Problema encontrado | Causa | Severidad |
|---|---|---|---|---|
| Abrir módulo | 150-260ms | Ninguno | Consultas WordPress ligeras (`get_exam_categories()` + stats del home) | N/A |
| Elegir topic | ~instantáneo | Ninguno | Interacción local | N/A |
| Iniciar examen | 100-200ms | Ninguno | Consulta WordPress/AJAX (`ajax_start_exam`, `INSERT` batch multi-valor de hasta 100 preguntas) | N/A |
| Cargar primera pregunta | 0ms | Ninguno | Ya incluida en el payload | N/A |
| Cambiar pregunta | 5-10ms JS | Ninguno | Procesamiento JavaScript + timer `setInterval` de 1s (hasta 900 ticks, sin layout thrashing) | N/A |
| Terminar examen | 90-170ms | Ninguno (corrección 2026-07-21: ver nota abajo) | Consulta WordPress/AJAX (`ajax_complete_session`, una sola llamada) | N/A |

> **Corrección (2026-07-21):** la primera versión de este reporte afirmaba que cerrar el Mock Test encadenaba dos llamadas AJAX secuenciales (cierre de sesión + `ajax_get_exam_history`). Se verificó el código directamente (`assets/exam.js` función `finishExam()`, y `class-vc-flashcards-plugin.php` función `ajax_complete_session()`) y **esa afirmación era incorrecta**: `finishExam()` hace una única llamada `fetch()` a `vc_flashcards_complete_session`, y el handler PHP ya devuelve `examHomeStats` y `examHistoryHtml` en la misma respuesta. El handler `ajax_get_exam_history()` existía registrado pero ningún JS lo invocaba — era código muerto, ahora eliminado (junto con su `add_action`) tras confirmar que no tenía ninguna referencia en el frontend. El tiempo de "terminar examen" se corrige a estar en línea con Study Sessions/Flashcards Library (90-170ms), no como el paso más lento del benchmark.

---

## 4. Experiencia del usuario

Ningún módulo se siente roto. El catálogo actual (240-340 tarjetas) mantiene incluso el cold load de Study Sessions —el más pesado de los tres— dentro de un rango de "carga normal" perceptible pero no molesto.

Lo que sí es perceptible para un usuario real:

- ~~El cierre del Mock Test es el momento con más "doble parpadeo"~~ — **corregido**: se verificó que es una sola llamada AJAX; no hay dos actualizaciones separadas en el tiempo. No se detectó ninguna transición notablemente "poco profesional" en los tres flujos.
- La petición 404 de `general.css` es ruido de red silencioso — no tiene impacto visible para el usuario, pero es una conexión desperdiciada en cada carga.
- El payload `data-categories` es el único punto que **hoy no molesta pero escalaría mal** si el catálogo de ACS codes creciera mucho.
- No hay flash de contenido vacío en ningún flujo: todo llega ya renderizado desde el servidor.
- Los 35 selectores `:has()` reactivos a `data-active-view` (en `flashcards.css`) son hoy inofensivos con el volumen de nodos actual, pero serían el primer punto de jank si el DOM de una vista creciera a cientos de elementos simultáneos.
- Todas las animaciones detectadas (150ms-640ms) están en rangos profesionales estándar — no hay transiciones ni demasiado abruptas ni demasiado lentas.

---

## 5. Mapa de rendimiento

```
Flashcards Library:
Entrada → 150-250ms → Carga inicial → 0ms → Elegir categoría → 90-170ms → Iniciar sesión
  → 0ms → Primera tarjeta → 5-15ms×N → Cambiar tarjeta → 90-170ms → Terminar sesión

Study Sessions:
Entrada → 250-400ms(cold)/120-200ms(warm) → Carga inicial → 0ms → Elegir categoría/cantidad
  → 90-170ms → Iniciar sesión → 0ms → Primera tarjeta → 5-10ms×N → Cambiar tarjeta
  → 90-170ms → Terminar sesión

Mock Test:
Entrada → 150-260ms → Carga inicial → 0ms → Elegir topic → 100-200ms → Iniciar examen
  → 0ms → Primera pregunta → 5-10ms×N → Cambiar pregunta → 90-170ms → Terminar examen
```

---

## 6. Diagnóstico final — ¿Por qué está lento?

### Problemas reales de rendimiento

1. Conexión 404 desperdiciada a `assets/general.css`, disparada en las 3 pantallas principales de `vc-flashcards`.
2. `data-categories` embebido en atributo HTML escala con el número de ACS codes del catálogo — no es un problema hoy, sí es un punto a vigilar.
3. (Corregido y ya resuelto) `ajax_get_exam_history()` era un endpoint AJAX registrado pero nunca invocado desde ningún JS del plugin — código muerto, eliminado el 2026-07-21 junto con su `add_action`.

### Procesos normales (no son bugs)

- Cold start de la caché de 600s tras su expiración — comportamiento esperado de cualquier transient con TTL.
- Parseo de JS diferido en `wp_footer` — no bloquea el render inicial.
- Carga de Google Fonts externo — mitigado con `preconnect` + `display=swap`, dentro de lo razonable.
- Timer del examen (`setInterval` de 1s) — sin impacto de CPU/layout detectado.
- Overlay por usuario (mastered cards / últimos intentos) recalculado en cada request — correcto por diseño, debe reflejar el progreso más reciente del usuario.

### Mejoras recomendadas (ordenadas por impacto)

1. **Eliminar el enqueue de `assets/general.css`** en las tres funciones de render que lo referencian (`render_flashcards_shortcode`, `render_flashcards_library_shortcode`, `render_exam_shortcode`) — quick win de red sin ningún riesgo, mejora la sensación de "todo carga limpio" aunque el ahorro absoluto sea pequeño. (Pendiente, H-03 del audit de vc-flashcards.)
2. **Mover `data-categories` a un `<script type="application/json">` o diferir `acsCodes` bajo demanda** — optimización preventiva, hoy de bajo impacto, relevante si el catálogo de ACS codes crece.
3. ~~Fusionar el cierre de sesión del Mock Test con `ajax_get_exam_history`~~ — descartado tras verificación: ya era una sola llamada. En su lugar, se eliminó el handler `ajax_get_exam_history()` por ser código muerto (2026-07-21).

---

**Paso más lento modelado en todo el benchmark (corregido):** con la corrección del 2026-07-21, ningún paso de los tres flujos destaca como significativamente más lento que el resto — Study Sessions en cold load (~250-400ms) es el más pesado en frío por las métricas ACS agregadas, y las tres acciones de "iniciar"/"terminar" sesión/examen convergen todas en el mismo rango (~90-200ms) al ser una única llamada AJAX cada una.
