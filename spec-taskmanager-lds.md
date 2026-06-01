# Mi Llamamiento — Especificación del Sistema
**Versión:** 2.0  
**Estado:** En construcción  
**Nombre de la app:** Mi Llamamiento  

---

## 1. Introducción y objetivo

El sistema, llamado **Mi Llamamiento**, es una aplicación web progresiva (PWA) de gestión de tareas diseñada para miembros de La Iglesia de Jesucristo de los Santos de los Últimos Días. Su propósito es facilitar la organización y el seguimiento de tareas dentro de los distintos llamamientos y áreas de servicio de la iglesia.

El sistema permite a cada usuario gestionar tareas personales y colaborar con otros miembros dentro de las áreas de servicio que le corresponden según su llamamiento. La estructura organizacional de la iglesia (estacas, barrios, ramas, llamamientos) determina automáticamente a qué áreas de servicio pertenece cada usuario.

**Stack tecnológico:** HTML5, CSS, JavaScript, PHP puro, MySQL. Arquitectura MVC. PWA offline-first responsive (celular y tablet) con service worker, IndexedDB, sincronización automática y Web Push API. Ver sección 11 para el detalle técnico.

---

## 2. Roles y actores

### 2.1 Roles globales

El sistema tiene un único tipo de usuario: **Usuario Registrado**. No existe un administrador del sistema.

### 2.2 Roles dentro de un área de servicio

| Rol | Descripción y permisos |
|---|---|
| **Miembro del área** | Puede ver todas las tareas del área, crear tareas, editar y eliminar sus propias tareas. No puede aprobar solicitudes de acceso ni modificar la estructura del área. |
| **Propietario del área** | Tiene todos los permisos del miembro, más: puede editar y eliminar cualquier tarea del área, aprobar o rechazar solicitudes de acceso, promover miembros al rol de propietario, remover miembros y eliminar el área. |

> El rol es por área: un usuario puede ser propietario en un área y miembro en otra.

### 2.3 Actores

| Actor | Descripción |
|---|---|
| Usuario no registrado | Persona que accede al sistema por primera vez o sin sesión activa. |
| Usuario registrado | Persona con cuenta activa. Puede ser miembro o propietario según el área. |
| Usuario miembro | Usuario registrado con rol de miembro dentro de un área específica. |
| Propietario del área | Usuario registrado con rol de propietario dentro de un área específica. |
| Sistema | Acciones automáticas realizadas por la plataforma (notificaciones, reasignaciones, aprobaciones automáticas, etc.). |

---

## 3. Conceptos fundamentales

### 3.1 Jerarquía organizacional

El sistema organiza a los usuarios en torno a tres conceptos jerárquicos:

| Concepto | Definición | Ejemplo |
|---|---|---|
| **Nivel** | Agrupación mayor de la estructura eclesiastica. Define el ámbito del llamamiento. | Estaca / Barrio / Rama |
| **Área de Servicio** | Subdivisión dentro de un nivel. Es la unidad funcional donde se gestionan tareas. Se asigna automáticamente según el llamamiento del usuario. | Presidencia de Estaca, Cuórum de Élderes, Sociedad de Socorro |
| **Llamamiento** | El rol específico que ocupa una persona dentro de un Área de Servicio. | Presidente, 1er Consejera, Secretario |

### 3.2 Área de Servicio Personal

Todo usuario tiene un Área de Servicio Personal con las siguientes características:

- Se crea automáticamente al registrarse.
- No está asociada a ningún llamamiento eclesiastico.
- Nunca puede ser eliminada.
- No requiere aprobación para acceder.
- Cuando un área de servicio eclesiastica se elimina, sus tareas migran al Área Personal de cada responsable.

### 3.3 Áreas de Servicio eclesiasticas

Las áreas eclesiasticas se asignan automáticamente al registrar un llamamiento. El usuario no las crea manualmente. Existen tres niveles: **Estaca**, **Barrio** y **Rama**.

> El Distrito es equivalente a la Estaca pero está fuera del alcance de esta versión del sistema.

---

## 4. Estructura de llamamientos y áreas de servicio

### 4.1 Flujo de registro de llamamiento

Cuando un usuario registra un llamamiento, el proceso es:

| Paso | Acción del usuario | Comportamiento del sistema |
|---|---|---|
| 1 | Selecciona el nivel: Estaca, Barrio o Rama | Filtra las áreas de servicio disponibles para ese nivel y la unidad del usuario. |
| 2 | Selecciona el Área de Servicio | Muestra los llamamientos disponibles dentro de esa área. |
| 3 | Selecciona el llamamiento específico | Determina automáticamente las áreas a asignar, incluyendo solapamientos. |
| 4 | Guarda el perfil | Crea el acceso con estado "Pendiente". Notifica al presidente vía push y lo registra en su panel de solicitudes. |
| 5 | Espera aprobación | El área aparece en el dashboard con estado "Pendiente". No puede acceder a las tareas. |
| 6a | El presidente aprueba | El acceso se activa. El usuario puede ver y gestionar tareas del área. |
| 6b | El presidente rechaza | El acceso queda bloqueado. El usuario recibe notificación indicando que debe corregir su llamamiento. |
| 7 (tras rechazo) | El usuario corrige su llamamiento | La solicitud corregida vuelve al flujo completo desde el paso 4. No se aprueba automáticamente por haber sido revisada antes. |

> Si no existe ningún usuario registrado con autoridad para aprobar en esa unidad y nivel, el sistema aprueba automáticamente el acceso al guardar el perfil.

### 4.2 Reglas de aprobación por tipo de llamamiento

| Llamamiento solicitado | Quién aprueba (Estaca) | Quién aprueba (Barrio/Rama) |
|---|---|---|
| Presidente o líder de cualquier área de servicio | Presidencia de Estaca (Presidente, Consejeros) o sus Secretarios | Obispado (Obispo, Consejeros) o sus Secretarios / Presidencia de Rama (Presidente, Consejeros) o sus Secretarios |
| Consejero, Secretario u otro rol dentro de un área | El Presidente de esa Área de Servicio | El Presidente de esa Área de Servicio |
| Cualquier llamamiento de Presidencia de Estaca | Auto-aprobado si no hay nadie superior registrado | — |
| Cualquier llamamiento de Obispado o Presidencia de Rama | — | Auto-aprobado si no hay nadie superior registrado |
| Maestro/a de Seminario e Instituto | Presidencia de Estaca o sus Secretarios (el Supervisor no aprueba) | — |
| Llamamiento rechazado y corregido | Vuelve al flujo completo de aprobación, sin excepción | Vuelve al flujo completo de aprobación, sin excepción |

### 4.3 Nivel Estaca

#### Presidencia de Estaca
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Presidente de Estaca | Auto-aprobado si no hay superior registrado | Accede a TODAS las áreas de nivel Estaca de su estaca |
| 1er Consejero de Presidencia de Estaca | Presidente de Estaca | Accede a todas las áreas de Estaca. Puede aprobar llamamientos de Estaca |
| 2do Consejero de Presidencia de Estaca | Presidente de Estaca | Ídem 1er Consejero |
| Secretario de Estaca | Presidente de Estaca | Accede también al área Consejo de Estaca |
| Secretario Ejecutivo de Estaca | Presidente de Estaca | Accede también al área Consejo de Estaca |

#### Sumo Consejo
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Miembro del Sumo Consejo | Presidente de Estaca o Consejeros | Accede también al área Consejo de Estaca |

#### Consejo de Estaca
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Miembro del Consejo de Estaca | Presidente de Estaca o Consejeros | Acceso exclusivamente por solapamiento |

> No se accede directamente a esta área. Se asigna automáticamente a: Miembros del Sumo Consejo, Presidentes/as de Sociedad de Socorro, Hombres Jóvenes, Mujeres Jóvenes, Escuela Dominical y Primaria de Estaca, y los Secretarios de Estaca (Secretario y Secretario Ejecutivo). Los miembros de la Presidencia de Estaca acceden por su solapamiento general.

#### Presidencia de Sociedad de Socorro de Estaca
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Presidenta | Presidente de Estaca o Consejeros | Accede también al área Consejo de Estaca |
| 1er Consejera | Presidenta de Sociedad de Socorro | |
| 2da Consejera | Presidenta de Sociedad de Socorro | |
| Secretaria | Presidenta de Sociedad de Socorro | |

#### Presidencia de Hombres Jóvenes de Estaca
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Presidente | Presidente de Estaca o Consejeros | Debe ser Miembro del Sumo Consejo (regla organizacional). Accede al Consejo de Estaca |
| 1er Consejero | Presidente de Hombres Jóvenes | |
| 2do Consejero | Presidente de Hombres Jóvenes | |
| Secretario | Presidente de Hombres Jóvenes | |

#### Presidencia de Mujeres Jóvenes de Estaca
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Presidenta | Presidente de Estaca o Consejeros | Accede también al área Consejo de Estaca |
| 1er Consejera | Presidenta de Mujeres Jóvenes | |
| 2da Consejera | Presidenta de Mujeres Jóvenes | |
| Secretaria | Presidenta de Mujeres Jóvenes | |

#### Presidencia de Primaria de Estaca
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Presidenta | Presidente de Estaca o Consejeros | Accede también al área Consejo de Estaca |
| 1er Consejera | Presidenta de Primaria | |
| 2da Consejera | Presidenta de Primaria | |
| Secretaria | Presidenta de Primaria | |

#### Presidencia de Escuela Dominical de Estaca
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Presidente | Presidente de Estaca o Consejeros | Debe ser Miembro del Sumo Consejo (regla organizacional). Accede al Consejo de Estaca |
| 1er Consejero | Presidente de Escuela Dominical | |
| 2do Consejero | Presidente de Escuela Dominical | |
| Secretario | Presidente de Escuela Dominical | |

#### Templo e Historia Familiar de Estaca
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Consultor de Templo e Historia Familiar | Presidente de Estaca o Consejeros | |

#### Autosuficiencia y Bienestar
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Especialista de Autosuficiencia y Bienestar | Presidente de Estaca o Consejeros | |

#### Jóvenes Adultos Solteros
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Líder de Jóvenes Adultos Solteros | Presidente de Estaca o Consejeros | |

#### Jóvenes Adultos
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Líder de Jóvenes Adultos | Presidente de Estaca o Consejeros | |

#### Comunicaciones
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Director de Comunicaciones | Presidente de Estaca o Consejeros | |
| Director Auxiliar de Comunicaciones | Director de Comunicaciones | |
| Especialista en Comunicación | Director de Comunicaciones | |

#### Auditorías Financieras
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Presidente del Comité de Auditorías | Presidente de Estaca o Consejeros | Debe ser Consejero de la Presidencia de Estaca (regla organizacional) |
| Miembro del Comité de Auditorías | Presidente del Comité de Auditorías | |
| Auditor | Presidente del Comité de Auditorías | |

#### Música
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Coordinador/a de Música | Presidente de Estaca o Consejeros | |
| Director/a de Coro | Coordinador/a de Música | |
| Pianista de Coro | Coordinador/a de Música | |

#### Seminario e Instituto
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Supervisor/a de Seminario | Presidente de Estaca o Consejeros | |
| Maestro/a de Seminario | Presidencia de Estaca o sus secretarios | Las líneas de Seminario e Instituto son independientes entre sí. El Supervisor NO aprueba |
| Supervisor/a de Instituto | Presidente de Estaca o Consejeros | |
| Maestro/a de Instituto | Presidencia de Estaca o sus secretarios | El Supervisor NO aprueba |

#### Tecnología
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Especialista | Presidente de Estaca o Consejeros | |

---

### 4.4 Nivel Barrio

#### Obispado
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Obispo | Auto-aprobado si no hay superior registrado | Accede a: Consejo de Barrio, Primaria, Mujeres Jóvenes, Escuela Dominical y Sociedad de Socorro |
| 1er Consejero de Obispado | Obispo | Accede a: Consejo de Barrio, Primaria, Mujeres Jóvenes y Escuela Dominical |
| 2do Consejero de Obispado | Obispo | Accede a: Consejo de Barrio, Primaria, Mujeres Jóvenes y Escuela Dominical |
| Secretario de Barrio | Obispo | |
| Secretario Ejecutivo de Barrio | Obispo | |

#### Cuórum de Élderes
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Presidente | Obispo o Consejeros de Obispado | Accede también a: Consejo de Barrio, Templo e Historia Familiar, Obra Misional |
| 1er Consejero | Presidente del Cuórum de Élderes | Accede también a: Templo e Historia Familiar, Obra Misional |
| 2do Consejero | Presidente del Cuórum de Élderes | Accede también a: Templo e Historia Familiar, Obra Misional |
| Secretario | Presidente del Cuórum de Élderes | |

#### Sociedad de Socorro
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Presidenta | Obispo o Consejeros de Obispado | Accede también a: Consejo de Barrio, Templo e Historia Familiar, Obra Misional |
| 1er Consejera | Presidenta de Sociedad de Socorro | Accede también a: Templo e Historia Familiar, Obra Misional |
| 2da Consejera | Presidenta de Sociedad de Socorro | Accede también a: Templo e Historia Familiar, Obra Misional |
| Secretaria | Presidenta de Sociedad de Socorro | |

#### Mujeres Jóvenes
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Presidenta | Obispo o Consejeros de Obispado | Accede también al área Consejo de Barrio |
| 1er Consejera | Presidenta de Mujeres Jóvenes | |
| 2da Consejera | Presidenta de Mujeres Jóvenes | |
| Secretaria | Presidenta de Mujeres Jóvenes | |

#### Escuela Dominical
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Presidente/a | Obispo o Consejeros de Obispado | Accede también al área Consejo de Barrio |
| 1er Consejero/a | Presidente/a de Escuela Dominical | |
| 2do/da Consejero/a | Presidente/a de Escuela Dominical | |
| Secretario/a | Presidente/a de Escuela Dominical | |

#### Primaria
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Presidenta | Obispo o Consejeros de Obispado | Accede también al área Consejo de Barrio |
| 1er Consejera | Presidenta de Primaria | |
| 2da Consejera | Presidenta de Primaria | |
| Secretaria | Presidenta de Primaria | |

#### Obra Misional
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Líder Misional | Obispo o Consejeros de Obispado | Acceso por solapamiento desde Cuórum de Élderes y Sociedad de Socorro |
| Misionero/a de Barrio | Líder Misional | |

#### Templo e Historia Familiar
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Consultor/a de Templo e Historia Familiar | Obispo o Consejeros de Obispado | Acceso por solapamiento desde Cuórum de Élderes y Sociedad de Socorro |

#### Consejo de Barrio
| Llamamiento | Aprobado por | Observaciones |
|---|---|---|
| Miembro del Consejo de Barrio | Obispo o Consejeros de Obispado | Acceso exclusivamente por solapamiento |

> No se accede directamente. Se asigna automáticamente a: Obispo y sus Consejeros, Presidentes/as de Cuórum de Élderes, Sociedad de Socorro, Mujeres Jóvenes, Escuela Dominical y Primaria.

---

### 4.5 Nivel Rama

La estructura de la Rama es idéntica a la del Barrio con las siguientes diferencias de nomenclatura:

| En Barrio se llama | En Rama se llama |
|---|---|
| Obispado | Presidencia de Rama |
| Obispo | Presidente de Rama |
| 1er Consejero de Obispado | 1er Consejero de Presidencia de Rama |
| 2do Consejero de Obispado | 2do Consejero de Presidencia de Rama |
| Secretario de Barrio | Secretario de Rama |
| Secretario Ejecutivo de Barrio | Secretario Ejecutivo de Rama |
| Consejo de Barrio | Consejo de Rama |
| Miembro del Consejo de Barrio | Miembro del Consejo de Rama |
| Misionero/a de Barrio | Misionero/a de Rama |

Todas las reglas de solapamiento, acceso y aprobación descriptas para el Barrio aplican de forma idéntica a la Rama.

---

## 5. Reglas de solapamiento de áreas de servicio

El solapamiento ocurre cuando un llamamiento otorga acceso automático a áreas adicionales. Estos accesos NO requieren aprobación separada: se activan junto con el acceso principal y se revocan junto con él (a menos que otro llamamiento activo del usuario los mantenga).

### 5.1 Nivel Estaca

| Llamamiento | Áreas de servicio adicionales que recibe |
|---|---|
| Presidente de Estaca | Todas las áreas de servicio de nivel Estaca de su estaca |
| 1er y 2do Consejero de Presidencia de Estaca | Todas las áreas de servicio de nivel Estaca de su estaca |
| Miembro del Sumo Consejo | Consejo de Estaca |
| Presidente de Hombres Jóvenes de Estaca | Consejo de Estaca |
| Presidenta de Mujeres Jóvenes de Estaca | Consejo de Estaca |
| Presidenta de Sociedad de Socorro de Estaca | Consejo de Estaca |
| Presidente de Escuela Dominical de Estaca | Consejo de Estaca |
| Presidenta de Primaria de Estaca | Consejo de Estaca |
| Secretario de Estaca | Consejo de Estaca |
| Secretario Ejecutivo de Estaca | Consejo de Estaca |

### 5.2 Nivel Barrio y Rama

| Llamamiento | Áreas de servicio adicionales que recibe |
|---|---|
| Obispo / Presidente de Rama | Consejo de Barrio/Rama, Primaria, Mujeres Jóvenes, Escuela Dominical, Sociedad de Socorro |
| 1er y 2do Consejero de Obispado / de Presidencia de Rama | Consejo de Barrio/Rama, Primaria, Mujeres Jóvenes, Escuela Dominical |
| Presidente del Cuórum de Élderes | Consejo de Barrio/Rama, Templo e Historia Familiar, Obra Misional |
| 1er y 2do Consejero del Cuórum de Élderes | Templo e Historia Familiar, Obra Misional |
| Presidenta de Sociedad de Socorro | Consejo de Barrio/Rama, Templo e Historia Familiar, Obra Misional |
| 1er y 2da Consejera de Sociedad de Socorro | Templo e Historia Familiar, Obra Misional |
| Presidenta de Mujeres Jóvenes | Consejo de Barrio/Rama |
| Presidente/a de Escuela Dominical | Consejo de Barrio/Rama |
| Presidenta de Primaria | Consejo de Barrio/Rama |

---

## 6. Ciclo de vida de un área de servicio

| Estado | Descripción |
|---|---|
| **Pendiente** | El usuario registró el llamamiento. El área aparece en su dashboard pero no puede acceder a las tareas. El presidente fue notificado. |
| **Activa** | El presidente aprobó el acceso. El usuario puede ver y gestionar tareas dentro del área. |
| **Rechazada** | El presidente rechazó la solicitud. El área no es accesible. El usuario debe corregir su llamamiento y volver a solicitar (nuevo ciclo de aprobación completo). |
| **Sin responsable** | El usuario modificó o eliminó su llamamiento. Las tareas que le pertenecían quedan dentro del área sin responsable, hasta que un miembro las reasigne o alguien se las autoasigne. |
| **Eliminada** | No quedan usuarios con ese llamamiento en la unidad. Las tareas migran al Área Personal de cada usuario que tenía tareas asignadas. |

---

## 7. Reglas de negocio globales

1. El Área Personal se crea automáticamente al registrarse y nunca puede eliminarse.
2. La sesión persiste indefinidamente hasta que el usuario cierre sesión de forma manual.
3. El perfil eclesiastico (estaca/barrio o rama/llamamiento) es obligatorio para acceder al sistema.
4. Las estacas, barrios, ramas y llamamientos son recursos compartidos en toda la plataforma. Una vez ingresados por cualquier usuario, quedan disponibles para todos.
5. Un usuario puede pertenecer a múltiples áreas de servicio simultáneamente.
6. Un usuario puede tener llamamientos simultáneos en diferentes niveles (ej: Estaca y Barrio). El sistema lo permite sin restricciones.
7. Las áreas de servicio eclesiasticas se asignan automáticamente al registrar un llamamiento. El usuario no las crea manualmente.
8. Los accesos por solapamiento se activan junto con el acceso principal y no requieren aprobación separada.
9. Los accesos por solapamiento se revocan junto con el llamamiento que los originaba, salvo que otro llamamiento activo del usuario los mantenga.
10. Las tareas son del área, no del usuario. Al eliminarse un área, las tareas migran al Área Personal de cada responsable.
11. Las tareas cuyo responsable fue removido o cambió de llamamiento quedan sin responsable asignado (campo nulo) dentro del área.
12. Un usuario no puede ver tareas de áreas a las que no pertenece, aunque comparta otra área con el autor de esas tareas.
13. Los enlaces de invitación por email tienen validez de 7 días y son de un solo uso.
14. Una solicitud de acceso rechazada y corregida siempre vuelve al flujo completo de aprobación, sin excepción.
15. Si no existe ningún usuario con autoridad para aprobar en una unidad y nivel, el sistema aprueba automáticamente.

---

## 8. Resumen de permisos por actor

| Acción | Miembro | Propietario | Creador tarea | Observaciones |
|---|---|---|---|---|
| Ver tareas del área | Sí | Sí | Sí | Todos los miembros |
| Crear tareas | Sí | Sí | — | |
| Editar tarea propia | Sí | Sí | Sí | |
| Editar tarea ajena | No | Sí | — | Solo propietarios del área |
| Eliminar tarea propia | Sí | Sí | Sí | |
| Eliminar tarea ajena | No | Sí | — | Solo propietarios del área |
| Crear subtareas | Sí | Sí | — | Cualquier miembro |
| Aprobar/rechazar solicitudes de acceso | No | Sí | — | Según reglas de sección 4.2 |
| Promover a propietario | No | Sí | — | |
| Remover miembros | No | Sí | — | |
| Eliminar área | No | Sí | — | No aplica al Área Personal |

---

## 9. Casos de uso

### CU-01 — Registro con Google

| Campo | Detalle |
|---|---|
| **Actor** | Usuario no registrado |
| **Descripción** | Un usuario nuevo accede a la aplicación e inicia el proceso de registro utilizando su cuenta de Google. Al finalizar completa su perfil eclesiastico obligatorio. |
| **Precondiciones** | El usuario no tiene cuenta en el sistema. Tiene una cuenta de Google activa. |
| **Flujo principal** | 1. El usuario accede a la URL de la aplicación. 2. El sistema detecta que no hay sesión activa y muestra la pantalla de bienvenida con el botón "Iniciar sesión con Google". 3. El usuario presiona el botón. 4. El sistema redirige al flujo de OAuth 2.0 de Google. 5. El usuario selecciona su cuenta y otorga permisos. 6. Google devuelve un token al sistema. 7. El sistema verifica el token, detecta que el email no existe en la BD y crea un nuevo usuario. 8. El sistema crea automáticamente el Área Personal. 9. El sistema redirige al formulario de perfil eclesiastico obligatorio. 10. El usuario completa estaca/barrio o rama y al menos un llamamiento (ver CU-05). 11. El sistema guarda el perfil, asigna las áreas de servicio correspondientes y establece la sesión persistente. 12. El sistema redirige al dashboard. |
| **Flujos alternativos** | 7a. El email ya existe en la BD: se trata como login (ver CU-02). 10a. El usuario cierra el formulario sin completarlo: el sistema no permite continuar. |
| **Postcondiciones** | Usuario con cuenta activa, Área Personal creada, perfil eclesiastico completo, sesión activa. |
| **Reglas** | R1: El email de Google es el identificador único. R2: El perfil eclesiastico es obligatorio para acceder. R3: El Área Personal se crea automáticamente y nunca puede eliminarse. |

---

### CU-02 — Inicio de sesión con Google

| Campo | Detalle |
|---|---|
| **Actor** | Usuario registrado |
| **Descripción** | Un usuario con cuenta existente inicia sesión mediante Google OAuth. |
| **Precondiciones** | El usuario tiene cuenta activa en el sistema. |
| **Flujo principal** | 1. El usuario accede a la URL. 2. El sistema muestra la pantalla de bienvenida. 3. El usuario presiona "Iniciar sesión con Google". 4. El sistema redirige al flujo OAuth 2.0. 5. El usuario selecciona su cuenta. 6. Google devuelve el token. 7. El sistema verifica el token y encuentra el email en la BD. 8. El sistema genera un token de sesión, lo guarda en la BD y lo envía al frontend. 9. El frontend almacena el token en localStorage. 10. El sistema redirige al dashboard. |
| **Postcondiciones** | Sesión activa que persiste hasta cierre manual. |
| **Reglas** | R1: La sesión no expira por tiempo, solo por cierre manual. R2: Un usuario puede tener sesiones activas en múltiples dispositivos. |

---

### CU-03 — Cierre de sesión

| Campo | Detalle |
|---|---|
| **Actor** | Usuario registrado |
| **Descripción** | El usuario finaliza su sesión activa en el dispositivo actual. |
| **Precondiciones** | El usuario tiene una sesión activa. |
| **Flujo principal** | 1. El usuario accede al menú de perfil. 2. Selecciona "Cerrar sesión". 3. El sistema muestra una confirmación. 4. El usuario confirma. 5. El sistema invalida el token en la BD. 6. El frontend elimina el token de localStorage. 7. El sistema redirige a la pantalla de bienvenida. |
| **Postcondiciones** | La sesión queda invalidada. El usuario debe autenticarse nuevamente. |

---

### CU-04 — Detección de entorno e instalación PWA

| Campo | Detalle |
|---|---|
| **Actor** | Cualquier usuario |
| **Descripción** | El sistema detecta si la aplicación se ejecuta en un navegador y muestra un banner invitando a instalarla como PWA. Si ya está instalada, no muestra el banner. |
| **Precondiciones** | El usuario accede desde un navegador (no desde la app instalada). |
| **Flujo principal** | 1. El usuario accede a la URL. 2. El sistema verifica `window.matchMedia('(display-mode: standalone)')`. 3. Si es falso (navegador), escucha el evento `beforeinstallprompt`. 4a. En desktop o Android: muestra banner "Instalá la app para una mejor experiencia" con botón "Instalar". 4b. En iOS: muestra banner con instrucciones manuales: "Presioná el botón Compartir y luego Agregar a pantalla de inicio". 5. El usuario presiona "Instalar" (Android/desktop). 6. El sistema invoca el prompt nativo. 7. El usuario acepta. 8. La aplicación queda instalada. |
| **Flujos alternativos** | 2a. `display-mode: standalone` es verdadero: la app ya está instalada, no se muestra ningún banner. 5a. El usuario cierra el banner sin instalar: el banner no vuelve a aparecer en esa sesión. |
| **Postcondiciones** | Si el usuario instaló la app, la próxima apertura será en modo standalone sin banner. |
| **Reglas** | R1: El banner solo se muestra si la app no está instalada. |

---

### CU-05 — Completar perfil eclesiastico (primer acceso)

| Campo | Detalle |
|---|---|
| **Actor** | Usuario recién registrado |
| **Descripción** | Luego del registro, el usuario completa su información eclesiastica obligatoria: estaca, barrio o rama, y al menos un llamamiento. |
| **Precondiciones** | El usuario acaba de registrarse y no tiene perfil eclesiastico completo. |
| **Flujo principal** | 1. El sistema muestra el formulario de perfil eclesiastico. 2. El usuario selecciona el tipo de unidad mayor: "Estaca". 3. El sistema muestra un selector con todas las estacas existentes en la BD. 4. El usuario selecciona su estaca. Si no existe, selecciona "No está en la lista", indica el tipo (Estaca) y escribe el nombre; el sistema lo agrega y lo selecciona. 5. El sistema muestra las unidades (barrios/ramas) asociadas a la estaca seleccionada. 6. El usuario selecciona su barrio o rama. Si no existe, selecciona "No está en la lista", indica el tipo (Barrio/Rama) y escribe el nombre. 7. El usuario agrega al menos un llamamiento: selecciona el nivel (Estaca/Barrio/Rama), luego el área de servicio, luego el llamamiento. Si no existe, lo ingresa manualmente. 8. El usuario puede agregar más llamamientos repitiendo el paso 7. 9. El usuario presiona "Guardar perfil". 10. El sistema valida que todos los campos obligatorios estén completos. 11. El sistema guarda el perfil, crea los accesos a las áreas de servicio correspondientes y redirige al dashboard. |
| **Flujos alternativos** | 10a. Falta algún campo obligatorio: el sistema resalta los campos vacíos y no permite continuar. |
| **Postcondiciones** | Perfil eclesiastico completo. Nuevas estacas, unidades o llamamientos ingresados quedan disponibles para todos los usuarios. Áreas de servicio asignadas con estado según reglas de aprobación. |
| **Reglas** | R1: Estaca y barrio/rama son obligatorios. R2: Al menos un llamamiento es obligatorio. R3: Estacas, barrios, ramas y llamamientos son recursos compartidos en todo el sistema. |

---

### CU-06 — Editar perfil eclesiastico

| Campo | Detalle |
|---|---|
| **Actor** | Usuario registrado |
| **Descripción** | El usuario modifica su información eclesiastica: puede cambiar su estaca/barrio o rama, o agregar/quitar llamamientos. |
| **Precondiciones** | El usuario tiene sesión activa y perfil completo. |
| **Flujo principal** | 1. El usuario accede a "Mi perfil" desde el menú. 2. El sistema muestra el perfil actual con todos los campos editables. 3. El usuario realiza los cambios deseados (mismo flujo que CU-05). 4. El usuario presiona "Guardar cambios". 5. El sistema valida y guarda. El impacto sobre las áreas de servicio se gestiona según CU-22. |
| **Postcondiciones** | Perfil actualizado guardado. |
| **Reglas** | R1: No se puede quitar el único llamamiento; debe quedar siempre al menos uno. |

---

### CU-07 — Ver y navegar entre áreas de servicio

| Campo | Detalle |
|---|---|
| **Actor** | Usuario registrado |
| **Descripción** | El usuario ve todas sus áreas de servicio (Personal y eclesiasticas activas) y puede cambiar de una a otra rápidamente. |
| **Precondiciones** | El usuario tiene sesión activa. |
| **Flujo principal** | 1. El usuario accede al dashboard. 2. El sistema muestra todas las áreas del usuario: siempre "Personal" primero, luego las áreas eclesiasticas activas. Las áreas pendientes de aprobación se muestran con su estado pero no son accesibles. 3. El usuario selecciona un área activa. 4. El sistema muestra las tareas del área. 5. El usuario puede cambiar a otra área desde el selector lateral/superior sin volver al dashboard. |
| **Postcondiciones** | El usuario visualiza las tareas del área seleccionada. |

---

### CU-08 — Crear tarea

| Campo | Detalle |
|---|---|
| **Actor** | Miembro / Propietario del área |
| **Descripción** | El usuario crea una nueva tarea dentro de un área de servicio. |
| **Precondiciones** | El usuario pertenece al área con acceso activo. |
| **Flujo principal** | 1. El usuario está dentro de un área y presiona "Nueva tarea". 2. El sistema muestra el formulario. 3. El usuario completa: Título (obligatorio), Descripción (opcional), Fecha de inicio (opcional), Fecha de vencimiento (opcional), Responsable (por defecto el propio usuario; puede cambiarse por cualquier miembro activo del área). 4. El usuario guarda. 5. El sistema crea la tarea asociada al área, visible para todos sus miembros. |
| **Flujos alternativos** | 3a. El usuario no completa el título: el sistema no permite guardar. |
| **Postcondiciones** | La tarea existe dentro del área y es visible para todos sus miembros. |
| **Reglas** | R1: Solo el título es obligatorio. R2: El responsable debe ser un miembro activo del área. R3: La tarea pertenece al área donde fue creada. |

---

### CU-09 — Editar tarea

| Campo | Detalle |
|---|---|
| **Actor** | Creador de la tarea / Propietario del área |
| **Descripción** | El usuario modifica los datos de una tarea existente. |
| **Precondiciones** | El usuario es el creador de la tarea O es propietario del área. La tarea existe en el área. |
| **Flujo principal** | 1. El usuario accede al detalle de la tarea. 2. Presiona "Editar". 3. El sistema muestra el formulario con los datos actuales. 4. El usuario modifica los campos deseados. 5. El usuario guarda los cambios. 6. El sistema actualiza la tarea. |
| **Flujos alternativos** | 2a. El usuario es miembro pero no es el creador ni propietario del área: el botón "Editar" no aparece. |
| **Postcondiciones** | La tarea queda actualizada y los cambios son visibles para todos los miembros del área. |

---

### CU-10 — Eliminar tarea

| Campo | Detalle |
|---|---|
| **Actor** | Creador de la tarea / Propietario del área |
| **Descripción** | El usuario elimina una tarea y todas sus subtareas del área. |
| **Precondiciones** | El usuario es el creador de la tarea O es propietario del área. |
| **Flujo principal** | 1. El usuario accede al detalle de la tarea. 2. Selecciona "Eliminar tarea". 3. El sistema muestra confirmación: "Se eliminarán la tarea y todas sus subtareas". 4. El usuario confirma. 5. El sistema elimina la tarea y sus subtareas permanentemente. |
| **Postcondiciones** | La tarea y sus subtareas ya no existen en el sistema. |
| **Reglas** | R1: La eliminación es permanente e irreversible. |

---

### CU-11 — Crear subtarea

| Campo | Detalle |
|---|---|
| **Actor** | Miembro / Propietario del área |
| **Descripción** | El usuario agrega una subtarea a una tarea existente. |
| **Precondiciones** | El usuario pertenece al área con acceso activo. La tarea padre existe. |
| **Flujo principal** | 1. El usuario accede al detalle de la tarea. 2. Presiona "Agregar subtarea". 3. El sistema muestra el formulario: Título (obligatorio), Descripción (opcional), Fecha de inicio (opcional), Fecha de fin (opcional). 4. El usuario completa y guarda. 5. La subtarea aparece anidada bajo la tarea padre. |
| **Postcondiciones** | La subtarea existe y es visible para todos los miembros del área. |
| **Reglas** | R1: Las subtareas no tienen responsable propio. R2: Solo hay un nivel de subtareas (sin sub-subtareas). |

---

### CU-12 — Ver tareas del área

| Campo | Detalle |
|---|---|
| **Actor** | Miembro / Propietario del área |
| **Descripción** | El usuario visualiza todas las tareas del área, incluyendo las de otros miembros. |
| **Precondiciones** | El usuario pertenece al área con acceso activo. |
| **Flujo principal** | 1. El usuario accede al área de servicio. 2. El sistema muestra todas las tareas del área, independientemente de quién las creó o quién es el responsable. 3. El usuario puede seleccionar cualquier tarea para ver su detalle y subtareas. |
| **Postcondiciones** | El usuario puede ver todas las tareas del área. |
| **Reglas** | R1: Un usuario solo ve las tareas de las áreas a las que pertenece con acceso activo. R2: No puede ver tareas de otras áreas que no comparte, aunque comparta otra área con el mismo usuario. |

---

### CU-13 — Invitar usuario existente a un área de servicio

| Campo | Detalle |
|---|---|
| **Actor** | Propietario del área |
| **Descripción** | El propietario invita a un usuario registrado a unirse a su área de servicio mediante su correo electrónico. |
| **Precondiciones** | El actor es propietario del área. El usuario invitado tiene cuenta en el sistema. |
| **Flujo principal** | 1. El propietario accede a "Compartir área de servicio". 2. El sistema muestra un campo para ingresar el email. 3. El propietario ingresa el email y confirma. 4. El sistema busca el email en la BD y lo encuentra. 5. El sistema crea una invitación con estado "pendiente". 6. El sistema envía una notificación push al usuario invitado. 7. La invitación aparece en la zona de notificaciones del usuario invitado. |
| **Flujos alternativos** | 4a. El email no pertenece a un usuario registrado: continúa en CU-14. 3b. El email ya es miembro del área: el sistema muestra "Este usuario ya pertenece al área". 3c. Ya existe una invitación pendiente: el sistema muestra "Ya existe una invitación pendiente para este usuario". |
| **Postcondiciones** | Existe una invitación pendiente. El usuario invitado recibió una notificación push. |
| **Reglas** | R1: Un usuario no puede ser invitado dos veces si ya tiene invitación pendiente o ya es miembro. |

---

### CU-14 — Invitar usuario no registrado a un área de servicio

| Campo | Detalle |
|---|---|
| **Actor** | Propietario del área |
| **Descripción** | El propietario invita a una persona sin cuenta. Esa persona recibe un email para registrarse y unirse al área. |
| **Precondiciones** | El actor es propietario del área. El email ingresado no existe en la BD. |
| **Flujo principal** | 1. Continúa desde el paso 4a de CU-13. 2. El sistema crea una invitación pendiente asociada al email. 3. El sistema envía un correo con el nombre del área, nombre del propietario y un enlace único con token temporal. 4. La persona hace click en el enlace. 5. El sistema valida el token y redirige al flujo de registro con Google (CU-01). 6. Al completar el registro, el sistema detecta la invitación pendiente y la acepta automáticamente. 7. El sistema redirige al dashboard mostrando el área compartida. |
| **Flujos alternativos** | 4a. El token expiró (más de 7 días): el sistema muestra "Esta invitación ya no es válida. Solicitá una nueva al propietario del área". |
| **Postcondiciones** | El nuevo usuario tiene cuenta y es miembro del área. |
| **Reglas** | R1: El enlace de invitación tiene validez de 7 días. R2: El enlace es de un solo uso. |

---

### CU-15 — Aceptar o rechazar invitación a un área

| Campo | Detalle |
|---|---|
| **Actor** | Usuario registrado (invitado) |
| **Descripción** | El usuario recibe una invitación push para unirse a un área y decide aceptarla o rechazarla. |
| **Precondiciones** | El usuario tiene una invitación pendiente. |
| **Flujo principal** | 1. El usuario ve la notificación push o accede a la zona de notificaciones. 2. El sistema muestra la invitación con el nombre del área y del propietario. 3. El usuario presiona "Aceptar". 4. El sistema agrega al usuario como miembro del área. 5. El sistema marca la invitación como "aceptada". 6. El área aparece en el dashboard del usuario. |
| **Flujos alternativos** | 3a. El usuario presiona "Rechazar": la invitación queda como "rechazada" y desaparece de las notificaciones. |
| **Postcondiciones** | Si aceptó: el usuario es miembro del área. Si rechazó: no hay cambios en el área. |

---

### CU-16 — Promover miembro a propietario del área

| Campo | Detalle |
|---|---|
| **Actor** | Propietario del área |
| **Descripción** | Un propietario otorga el rol de propietario a otro miembro del área. |
| **Precondiciones** | El actor es propietario del área. El usuario objetivo es miembro del área. |
| **Flujo principal** | 1. El propietario accede a la lista de miembros del área. 2. Selecciona un miembro y elige "Hacer propietario". 3. El sistema muestra confirmación. 4. El propietario confirma. 5. El sistema actualiza el rol. 6. El usuario promovido recibe una notificación. |
| **Postcondiciones** | El usuario promovido tiene rol de propietario en el área. |
| **Reglas** | R1: Solo los propietarios pueden promover a otros. |

---

### CU-17 — Remover miembro de un área

| Campo | Detalle |
|---|---|
| **Actor** | Propietario del área |
| **Descripción** | El propietario elimina a un miembro del área. Las tareas del miembro removido quedan sin responsable dentro del área. |
| **Precondiciones** | El actor es propietario del área. El usuario objetivo es miembro del área. |
| **Flujo principal** | 1. El propietario accede a la lista de miembros. 2. Selecciona el miembro y elige "Remover del área". 3. El sistema muestra confirmación: "Las tareas de este usuario en el área quedarán sin responsable asignado". 4. El propietario confirma. 5. El sistema elimina la membresía. 6. Las tareas donde ese usuario era responsable quedan con responsable nulo. 7. El área desaparece del dashboard del usuario removido. |
| **Postcondiciones** | El usuario ya no tiene acceso al área. Sus tareas en el área quedan sin responsable. |
| **Reglas** | R1: Un propietario no puede removerse a sí mismo si es el único propietario del área. |

---

### CU-18 — Eliminar área de servicio

| Campo | Detalle |
|---|---|
| **Actor** | Propietario del área |
| **Descripción** | Un propietario elimina un área de servicio. Las tareas del área se redistribuyen al Área Personal de cada responsable. |
| **Precondiciones** | El usuario es propietario del área. El área no es "Personal". |
| **Flujo principal** | 1. El propietario accede a la configuración del área. 2. Selecciona "Eliminar área de servicio". 3. El sistema muestra advertencia: "Esta acción es irreversible. Todas las tareas serán movidas al Área Personal de cada responsable". 4. El propietario confirma. 5. El sistema mueve cada tarea al Área Personal del usuario responsable. 6. Las subtareas se mueven junto con su tarea padre. 7. Las tareas sin responsable asignado se eliminan permanentemente. 8. El sistema elimina el área y todas sus membresías. 9. El sistema redirige al dashboard. |
| **Flujos alternativos** | 4a. El propietario cancela: no ocurre ningún cambio. |
| **Postcondiciones** | El área ya no existe. Cada tarea queda en el Área Personal del usuario responsable. |
| **Reglas** | R1: El Área Personal nunca puede eliminarse. R2: Las tareas sin responsable se eliminan junto con el área. |

---

### CU-19 — Registrar llamamiento y solicitar acceso al área de servicio

| Campo | Detalle |
|---|---|
| **Actor** | Usuario registrado |
| **Descripción** | El usuario agrega un llamamiento a su perfil, lo que genera automáticamente una solicitud de acceso al área de servicio correspondiente y a las adicionales por solapamiento. |
| **Precondiciones** | El usuario tiene sesión activa y perfil eclesiastico base completo. |
| **Flujo principal** | 1. El usuario accede a su perfil y selecciona "Agregar llamamiento". 2. Selecciona el nivel: Estaca, Barrio o Rama. 3. El sistema muestra las áreas de servicio disponibles para ese nivel y unidad. 4. El usuario selecciona el área de servicio. 5. El sistema muestra los llamamientos disponibles. 6. El usuario selecciona su llamamiento. 7. El sistema determina las áreas a asignar (propias + solapamientos). 8. El usuario guarda. 9. El sistema crea los accesos con estado "Pendiente". 10a. Si existe un presidente con autoridad registrado: le envía notificación push y registra la solicitud en su panel. 10b. Si no existe nadie con autoridad: aprueba automáticamente todos los accesos. 11. El usuario ve las áreas en su dashboard con estado "Pendiente de aprobación". |
| **Flujos alternativos** | 6a. El llamamiento no existe en la lista: el usuario puede ingresarlo manualmente. El nuevo llamamiento queda disponible para todos en el sistema. |
| **Postcondiciones** | El usuario tiene accesos a áreas de servicio en estado Pendiente o Activo. El presidente fue notificado si existía en el sistema. |
| **Reglas** | R1: Un usuario puede tener múltiples llamamientos simultáneos en diferentes niveles. R2: Los accesos por solapamiento se asignan automáticamente. R3: Los accesos por solapamiento no requieren aprobación separada. R4: Si no hay autoridad registrada, el sistema aprueba automáticamente. |

---

### CU-20 — Aprobar o rechazar solicitud de acceso a área de servicio

| Campo | Detalle |
|---|---|
| **Actor** | Presidente del área / Líder con autoridad de aprobación |
| **Descripción** | El presidente revisa las solicitudes pendientes de acceso a su área y aprueba o rechaza cada una. |
| **Precondiciones** | El actor tiene un llamamiento con autoridad de aprobación activo. Existe al menos una solicitud pendiente. |
| **Flujo principal** | 1. El presidente accede a "Solicitudes pendientes" en su dashboard o desde la notificación push. 2. El sistema muestra la lista: nombre del usuario, llamamiento solicitado y área. 3. El presidente selecciona una solicitud y presiona "Aprobar". 4. El sistema activa el acceso del usuario al área. 5. El usuario recibe una notificación: "Tu acceso al área X fue aprobado". 6. El área queda activa en el dashboard del usuario. |
| **Flujos alternativos** | 3a. El presidente presiona "Rechazar": el sistema solicita confirmación. El acceso queda bloqueado. El usuario recibe notificación indicando que debe revisar y corregir su llamamiento. Una nueva solicitud tras la corrección vuelve al flujo completo. |
| **Postcondiciones** | El usuario tiene acceso activo o rechazado al área. La solicitud desaparece de la lista de pendientes. |
| **Reglas** | R1: Solo puede aprobar quien tenga el rol de aprobador según las reglas de la sección 4.2. R2: El rechazo no elimina el llamamiento del perfil; el usuario debe corregirlo manualmente. R3: Una solicitud corregida siempre vuelve al flujo completo, sin excepción. |

---

### CU-21 — Cambiar o quitar un llamamiento

| Campo | Detalle |
|---|---|
| **Actor** | Usuario registrado |
| **Descripción** | El usuario modifica o elimina un llamamiento de su perfil. El sistema gestiona el impacto sobre las áreas de servicio asociadas. |
| **Precondiciones** | El usuario tiene sesión activa y al menos un llamamiento registrado. |
| **Flujo principal** | 1. El usuario accede a su perfil y selecciona el llamamiento a modificar o eliminar. 2. Si modifica: sigue el flujo de CU-19 para el nuevo llamamiento. 3. Si elimina: el sistema muestra advertencia sobre las tareas que quedarán sin responsable. 4. El usuario confirma. 5. El sistema revoca el acceso a las áreas exclusivas de ese llamamiento. 6. Las tareas donde el usuario era responsable quedan con responsable nulo. 7. Si el área queda sin ningún usuario con ese llamamiento: sus tareas migran al Área Personal de cada usuario que las tenía asignadas. 8. El área desaparece del dashboard del usuario. |
| **Flujos alternativos** | 4a. El usuario cancela: no ocurre ningún cambio. 5a. Otro llamamiento activo del usuario también da acceso a esa área por solapamiento: el área permanece activa. |
| **Postcondiciones** | El llamamiento fue removido. Las áreas exclusivas ya no son accesibles. Las tareas quedan sin responsable o migran según corresponda. |
| **Reglas** | R1: Si otro llamamiento activo mantiene el acceso al área por solapamiento, el área permanece activa. R2: Siempre debe quedar al menos un llamamiento en el perfil. R3: Los accesos por solapamiento se revocan junto con el llamamiento que los originaba, salvo que otro los mantenga. |

---

## 9-bis. Módulo de entrevistas

> Este módulo es una herramienta interna exclusiva de líderes y secretarios. Los miembros entrevistados **no interactúan con el sistema, no tienen cuenta y no deben saber de su existencia**. El objetivo central es evitar que las presidencias pierdan de vista sus entrevistas pendientes y mejorar el cumplimiento (problema actual: menos del 60% de las entrevistas de ministración trimestrales se realizan).

### 9-bis.1 Tipos de entrevista

El sistema distingue dos tipos de entrevista con dinámicas diferentes:

| Tipo | Quién entrevista | Naturaleza | Seguimiento |
|---|---|---|---|
| **Tipo A — Liderazgo** | Obispado, Presidencia de Rama, Presidencia de Estaca (y sus secretarios coordinan) | Reactiva y puntual. Disparada por una necesidad (recomendación del templo, extender o relevar llamamiento, seguimiento, entrevista personal, etc.). Muchas veces de única vez. | Lista de pendientes con fecha objetivo. Sin ciclo recurrente. |
| **Tipo B — Ministración** | Presidencia de Cuórum de Élderes y Presidencia de Sociedad de Socorro (y sus secretarios) | Proactiva y recurrente. Cada pareja ministrante debe ser entrevistada al menos una vez por trimestre calendario. | Tablero de cumplimiento trimestral con semáforo automático. |

### 9-bis.2 Parejas ministrantes (Tipo B)

- Una **pareja ministrante** está formada por dos miembros de la organización (Cuórum de Élderes o Sociedad de Socorro).
- En esta primera versión solo se registra **el nombre de los dos integrantes de la pareja**. A quiénes ministran (familias o personas asignadas) queda para una versión futura.
- Las parejas son datos del área de servicio correspondiente.
- Los nombres de los integrantes son texto libre; **no son usuarios del sistema**.

#### Permisos sobre parejas ministrantes

| Quién | Sobre qué parejas puede crear/editar |
|---|---|
| Presidente, Consejeros y Secretarios del Cuórum de Élderes | Las parejas de su propio Cuórum de Élderes |
| Presidenta, Consejeras y Secretarias de Sociedad de Socorro (barrio/rama) | Las parejas de su propia Sociedad de Socorro |
| Presidente, Consejeros y Secretarios de Presidencia de Estaca | Las parejas de los Cuórum de Élderes de todos los barrios/ramas **y** de las Sociedades de Socorro de todos los barrios/ramas de su estaca |
| Presidenta, Consejeras y Secretarias de Sociedad de Socorro de Estaca | Las parejas de las Sociedades de Socorro de todos los barrios/ramas de su estaca |

### 9-bis.2.1 Asignación de entrevistador responsable

Para evitar que la responsabilidad de entrevistar quede librada al azar (causa frecuente de incumplimiento), las parejas se distribuyen entre los miembros de la presidencia:

- Cada pareja ministrante tiene **un único entrevistador responsable**, que debe ser un miembro de la presidencia de la organización (presidente/a o consejeros/as).
- La distribución la pueden hacer el presidente/a, los consejeros/as **y** los secretarios/as de la organización.
- La asignación define **responsabilidad**, no autoría del registro: cualquiera de la presidencia o secretario puede marcar una entrevista como hecha, pero el sistema asume siempre que la entrevistó el responsable asignado, sin importar quién haya registrado. No se registra ni interesa quién marcó la entrevista.
- Una pareja puede no tener responsable asignado (estado inicial al crearse). En ese caso aparece como "sin asignar" en el tablero.
- **La asignación del responsable es persistente, no por trimestre.** Una vez asignado un entrevistador a una pareja, esa asignación queda vigente indefinidamente a través de todos los trimestres siguientes, hasta que alguien la cambie de forma explícita. Al cambiarla, el nuevo responsable queda vigente igualmente de forma persistente hasta el próximo cambio. Lo que se reinicia cada trimestre es el semáforo de cumplimiento (basado en las entrevistas del trimestre actual), nunca la asignación del responsable.

> Ejemplo: si Mario es asignado para entrevistar a la pareja Juan &amp; Lucas en el trimestre 1, en el trimestre 2 Mario sigue siendo el responsable automáticamente, sin necesidad de reasignar. Solo deja de serlo si alguien cambia el responsable de esa pareja.

> Aplica por igual a Presidencia de Cuórum de Élderes y Presidencia de Sociedad de Socorro. Donde el documento dice "presidencia", "consejeros" o "secretarios" se refiere a ambas organizaciones según corresponda.

### 9-bis.3 Semáforo de cumplimiento (Tipo B)

El plazo se cuenta por **trimestre calendario** (Ene–Mar, Abr–Jun, Jul–Sep, Oct–Dic), sin importar el día exacto. El estado de cada pareja depende de si fue entrevistada en el trimestre actual y, si no, de en qué mes del trimestre se está:

| Estado | Color | Condición |
|---|---|---|
| Entrevistada | Verde | Tiene al menos una entrevista registrada dentro del trimestre actual. |
| Pendiente (mes 1) | Amarillo | Sin entrevista en el trimestre y se está en el primer mes del trimestre. |
| Pendiente (mes 2) | Naranja | Sin entrevista en el trimestre y se está en el segundo mes del trimestre. |
| Vencida (mes 3) | Rojo | Sin entrevista en el trimestre y se está en el tercer (último) mes del trimestre. |

Una pareja **Pendiente** (amarillo/naranja/rojo) puede tener además una **entrevista agendada** con fecha y hora. En ese caso conserva su color de semáforo pero muestra visible la fecha/hora agendada (ej: "Agendada para el 15 jun 18:00"). Agendar no cambia el color; solo registrarla como hecha la pasa a verde.

- El tablero muestra un indicador global destacado: "X de Y al día (Z%)".
- Al comenzar un trimestre nuevo, el tablero muestra **ambos**: el cumplimiento del trimestre actual (que arranca en cero) y el histórico del trimestre anterior.
- Registrar una entrevista es **un solo gesto**: marcar la pareja como entrevistada. Por defecto usa la fecha de hoy, con opción de elegir otra fecha (por si se carga después). No se guardan notas ni resultados.

### 9-bis.3.1 Ciclo de una entrevista de ministración

Cada pareja, dentro de un trimestre, transita por estos estados:

| Estado | Descripción |
|---|---|
| **Pendiente** | Sin entrevista registrada en el trimestre y sin cita agendada. Color de semáforo según el mes (amarillo/naranja/rojo). |
| **Agendada** | Se coordinó una fecha y hora para la entrevista, pero aún no se realizó. Conserva el color de semáforo de pendiente, con la fecha/hora visible. |
| **Entrevistada** | La entrevista se marcó como realizada. Color verde. Una pareja puede pasar de Pendiente directo a Entrevistada sin pasar por Agendada; en ese caso la fecha de la entrevista es la del día en que se marca como hecha, salvo que se indique otra. |

- La fecha/hora agendada la puede establecer cualquiera de la presidencia o el secretario.
- Si se marca como entrevistada una pareja que estaba Agendada, se cierra la cita agendada.

> **La coordinación de la entrevista es externa al sistema.** El líder o secretario acuerda el día y la hora con la pareja por fuera (WhatsApp, en persona, etc.). El sistema solo **anota** la fecha ya acordada; no contacta a la pareja, no guarda sus datos de contacto y no le envía avisos. Las parejas son solo nombres (texto). El sistema es una herramienta interna de registro y seguimiento, no de coordinación con terceros. Esto aplica por igual a las entrevistas de liderazgo (Tipo A).

### 9-bis.4 Entrevistas de liderazgo (Tipo A)
- El líder o su secretario cargan manualmente una entrevista pendiente: a quién entrevistar (solo un nombre, texto libre, sin cuenta), el motivo y una fecha objetivo opcional.
- El sistema muestra la lista de entrevistas pendientes y recuerda mediante notificaciones.
- Al realizarse, se marca como completada. No se guardan notas ni resultados.
- No tiene semáforo trimestral porque no son recurrentes.

### 9-bis.5 Visibilidad supervisora

- La Presidencia de Estaca que supervisa varios barrios/ramas ve **el detalle completo de las parejas de cada barrio/rama**, no solo un porcentaje agregado.
- La Sociedad de Socorro de Estaca ve el detalle completo de las Sociedades de Socorro de todos los barrios/ramas de su estaca.

### 9-bis.5.1 Vistas del tablero

El tablero de ministración ofrece dos vistas:

- **Vista por área:** todas las parejas de la organización, con su responsable asignado visible.
- **Vista "mis parejas asignadas":** filtra solo las parejas donde el usuario actual es el entrevistador responsable. Pensada para que cada miembro de la presidencia vea de un vistazo lo que le toca.

### 9-bis.6 Recordatorios

- Recordatorios push proactivos a la presidencia y secretarios del área cuando hay parejas pendientes o vencidas (Tipo B) y cuando se acerca la fecha objetivo de una entrevista de liderazgo (Tipo A).
- Para parejas con responsable asignado, el recordatorio de pendiente/vencida se dirige principalmente al **entrevistador responsable**, además de la presidencia.
- **Recordatorio de cita agendada:** antes de la fecha/hora agendada, el sistema avisa al entrevistador responsable y a quien agendó la cita (si es una persona distinta).
- **Alerta de cita vencida:** si pasó la fecha/hora agendada y la entrevista no se marcó como hecha, el sistema alerta al responsable y a quien agendó.
- Resumen periódico del estilo: "Tenés 4 parejas por entrevistar este trimestre y 2 vencidas".

### 9-bis.7 Conexión con el módulo de tareas

- Una entrevista de cualquier tipo puede derivar en una tarea dentro del área de servicio correspondiente (ej: "La familia que ministra la pareja X necesita ayuda con Y"). Esta conexión es opcional y se apoya en el módulo de tareas ya definido.

---

### CU-22 — Registrar pareja ministrante

| Campo | Detalle |
|---|---|
| **Actor** | Presidencia o secretario de Cuórum de Élderes / Sociedad de Socorro (según permisos de 9-bis.2) |
| **Descripción** | El actor registra una nueva pareja ministrante dentro de su organización. |
| **Precondiciones** | El actor tiene acceso activo al área y permiso de edición de parejas. |
| **Flujo principal** | 1. El actor accede a la sección "Parejas ministrantes" del área. 2. Presiona "Nueva pareja". 3. Ingresa el nombre de los dos integrantes (texto libre). 4. Guarda. 5. El sistema crea la pareja con estado inicial según el semáforo del trimestre actual (sin entrevista). |
| **Postcondiciones** | La pareja existe y aparece en el tablero de cumplimiento. |
| **Reglas** | R1: Los integrantes son texto libre, no usuarios del sistema. R2: Solo se registran los nombres de la pareja; las asignaciones de ministración quedan fuera de esta versión. |

---

### CU-23 — Registrar entrevista de ministración

| Campo | Detalle |
|---|---|
| **Actor** | Presidencia o secretario de Cuórum de Élderes / Sociedad de Socorro |
| **Descripción** | El actor marca que una pareja ministrante fue entrevistada, reseteando su estado a verde para el trimestre actual. |
| **Precondiciones** | La pareja existe en el área. El actor tiene acceso activo al área. |
| **Flujo principal** | 1. El actor ve el tablero de cumplimiento. 2. En la fila de una pareja pendiente, presiona "Registrar". 3. El sistema registra la entrevista con la fecha de hoy. 4. La pareja pasa a estado verde (entrevistada) en el trimestre actual. 5. El indicador global de cumplimiento se recalcula. |
| **Flujos alternativos** | 2a. El actor elige una fecha distinta a hoy (por carga tardía): el sistema usa esa fecha para determinar a qué trimestre corresponde la entrevista. 2b. La pareja estaba en estado Agendada: al marcarla como hecha, se cierra la cita agendada. Si no se indica otra fecha, se asume como fecha de entrevista la de la cita agendada o la de hoy. |
| **Postcondiciones** | La pareja queda como entrevistada en el trimestre correspondiente. El cumplimiento se actualiza. |
| **Reglas** | R1: Registrar es un solo gesto. R2: No se guardan notas ni resultados. R3: El estado se evalúa por trimestre calendario, no por días exactos. R4: Se asume siempre que entrevistó el responsable asignado, sin importar quién registró. |

---

### CU-24 — Ver tablero de cumplimiento de ministración

| Campo | Detalle |
|---|---|
| **Actor** | Presidencia o secretario de Cuórum de Élderes / Sociedad de Socorro; Presidencia de Estaca o Sociedad de Socorro de Estaca (vista supervisora) |
| **Descripción** | El actor visualiza el estado de cumplimiento de todas las parejas ministrantes de su ámbito. |
| **Precondiciones** | El actor tiene acceso activo al área correspondiente. |
| **Flujo principal** | 1. El actor accede al tablero. 2. El sistema muestra el indicador global "X de Y al día (Z%)". 3. Lista cada pareja con su color de semáforo según el estado del trimestre actual. 4. Muestra el histórico del trimestre anterior. 5. Para roles supervisores de estaca, muestra el detalle completo de las parejas de cada barrio/rama bajo su ámbito. |
| **Postcondiciones** | El actor visualiza el cumplimiento actual e histórico. |
| **Reglas** | R1: La Presidencia de Estaca ve el detalle completo por barrio/rama, no solo agregados. R2: El semáforo usa cuatro estados según el mes del trimestre (ver 9-bis.3). |

---

### CU-25 — Crear entrevista de liderazgo

| Campo | Detalle |
|---|---|
| **Actor** | Líder o secretario de Obispado, Presidencia de Rama o Presidencia de Estaca |
| **Descripción** | El actor registra una entrevista de liderazgo pendiente para hacer seguimiento. |
| **Precondiciones** | El actor tiene acceso activo a un área de liderazgo. |
| **Flujo principal** | 1. El actor accede a la sección "Entrevistas de liderazgo". 2. Presiona "Nueva entrevista". 3. Ingresa el nombre de la persona a entrevistar (texto libre), el motivo y una fecha objetivo opcional. 4. Guarda. 5. La entrevista aparece en la lista de pendientes. |
| **Postcondiciones** | La entrevista pendiente queda registrada y genera recordatorios. |
| **Reglas** | R1: La persona a entrevistar es texto libre, no un usuario del sistema. R2: No se guardan notas ni resultados de la entrevista. |

---

### CU-26 — Completar entrevista de liderazgo

| Campo | Detalle |
|---|---|
| **Actor** | Líder o secretario de Obispado, Presidencia de Rama o Presidencia de Estaca |
| **Descripción** | El actor marca una entrevista de liderazgo como realizada. |
| **Precondiciones** | Existe una entrevista de liderazgo pendiente. |
| **Flujo principal** | 1. El actor ve la lista de entrevistas pendientes. 2. Presiona "Marcar como realizada" en una entrevista. 3. El sistema la marca como completada y la retira de los pendientes. |
| **Postcondiciones** | La entrevista queda completada y deja de generar recordatorios. |

---

### CU-27 — Recibir recordatorios de entrevistas

| Campo | Detalle |
|---|---|
| **Actor** | Sistema → Presidencia y secretarios del área |
| **Descripción** | El sistema envía recordatorios proactivos sobre entrevistas pendientes o vencidas. |
| **Precondiciones** | El actor tiene acceso activo a un área con entrevistas (Tipo A o B). |
| **Flujo principal** | 1. El sistema evalúa periódicamente el estado de las entrevistas. 2. Para ministración (Tipo B): si hay parejas pendientes o vencidas, envía push y muestra un resumen ("Tenés 4 parejas por entrevistar este trimestre y 2 vencidas"). 3. Para liderazgo (Tipo A): cuando se acerca o pasa la fecha objetivo de una entrevista, envía recordatorio. |
| **Postcondiciones** | El actor recibe notificaciones que lo ayudan a no descuidar sus entrevistas. |
| **Reglas** | R1: Los recordatorios se dirigen solo a líderes y secretarios, nunca a las personas entrevistadas. |

---

### CU-28 — Asignar entrevistador responsable a una pareja

| Campo | Detalle |
|---|---|
| **Actor** | Presidencia o secretario de Cuórum de Élderes / Sociedad de Socorro |
| **Descripción** | El actor distribuye las parejas ministrantes asignando a cada una un entrevistador responsable de la presidencia, para que la responsabilidad de entrevistar no quede librada al azar. |
| **Precondiciones** | La pareja existe en el área. El actor tiene permiso de edición de parejas (presidente/a, consejero/a o secretario/a). |
| **Flujo principal** | 1. El actor accede al tablero o a la pareja. 2. Selecciona "Asignar responsable". 3. El sistema muestra los miembros de la presidencia de la organización (presidente/a y consejeros/as). 4. El actor selecciona uno. 5. El sistema guarda la asignación. 6. La pareja muestra a su responsable en el tablero y aparece en la vista "mis parejas asignadas" de ese responsable. |
| **Flujos alternativos** | 4a. El actor reasigna a otro responsable: el sistema reemplaza la asignación anterior. |
| **Postcondiciones** | La pareja tiene un único entrevistador responsable. |
| **Reglas** | R1: El responsable debe ser miembro de la presidencia (presidente/a o consejero/a), no un secretario. R2: Una pareja tiene un solo responsable a la vez. R3: La asignación define responsabilidad, no autoría del registro. R4: La asignación es persistente: se mantiene a través de los trimestres hasta que se cambie explícitamente. No se redefine cada trimestre. |

---

### CU-29 — Agendar fecha y hora de una entrevista de ministración

| Campo | Detalle |
|---|---|
| **Actor** | Presidencia o secretario de Cuórum de Élderes / Sociedad de Socorro |
| **Descripción** | El actor coordina y registra la fecha y hora de una entrevista con una pareja, que pasa al estado "Agendada". |
| **Precondiciones** | La pareja existe y está en estado Pendiente en el trimestre actual. El actor tiene acceso activo al área. |
| **Flujo principal** | 1. El actor accede a la pareja en el tablero. 2. Selecciona "Agendar entrevista". 3. Ingresa fecha y hora. 4. Guarda. 5. La pareja pasa a estado Agendada: conserva su color de semáforo y muestra la fecha/hora visible. 6. El sistema programa un recordatorio para el entrevistador responsable y para quien agendó (si es distinto), antes de la cita. |
| **Flujos alternativos** | 3a. El actor modifica una cita ya agendada: el sistema actualiza la fecha/hora y reprograma los recordatorios. 5a. Pasa la fecha/hora agendada sin marcarse como hecha: el sistema envía una alerta de cita vencida al responsable y a quien agendó. |
| **Postcondiciones** | La pareja queda con una cita agendada y recordatorios programados. |
| **Reglas** | R1: Agendar no cambia el color del semáforo; solo registrar la entrevista como hecha la pasa a verde. R2: La fecha/hora la puede establecer cualquiera de la presidencia o el secretario. R3: Al marcar la entrevista como hecha, la cita agendada se cierra. |

---

## 10. Índice de casos de uso

| ID | Nombre | Módulo |
|---|---|---|
| CU-01 | Registro con Google | Autenticación |
| CU-02 | Inicio de sesión con Google | Autenticación |
| CU-03 | Cierre de sesión | Autenticación |
| CU-04 | Detección de entorno e instalación PWA | Autenticación |
| CU-05 | Completar perfil eclesiastico (primer acceso) | Perfil |
| CU-06 | Editar perfil eclesiastico | Perfil |
| CU-07 | Ver y navegar entre áreas de servicio | Áreas de servicio |
| CU-08 | Crear tarea | Tareas |
| CU-09 | Editar tarea | Tareas |
| CU-10 | Eliminar tarea | Tareas |
| CU-11 | Crear subtarea | Tareas |
| CU-12 | Ver tareas del área | Tareas |
| CU-13 | Invitar usuario existente a un área | Colaboración |
| CU-14 | Invitar usuario no registrado a un área | Colaboración |
| CU-15 | Aceptar o rechazar invitación | Colaboración |
| CU-16 | Promover miembro a propietario | Colaboración |
| CU-17 | Remover miembro de un área | Colaboración |
| CU-18 | Eliminar área de servicio | Áreas de servicio |
| CU-19 | Registrar llamamiento y solicitar acceso | Llamamientos |
| CU-20 | Aprobar o rechazar solicitud de acceso | Llamamientos |
| CU-21 | Cambiar o quitar un llamamiento | Llamamientos |
| CU-22 | Registrar pareja ministrante | Entrevistas |
| CU-23 | Registrar entrevista de ministración | Entrevistas |
| CU-24 | Ver tablero de cumplimiento de ministración | Entrevistas |
| CU-25 | Crear entrevista de liderazgo | Entrevistas |
| CU-26 | Completar entrevista de liderazgo | Entrevistas |
| CU-27 | Recibir recordatorios de entrevistas | Entrevistas |
| CU-28 | Asignar entrevistador responsable a una pareja | Entrevistas |
| CU-29 | Agendar fecha y hora de una entrevista de ministración | Entrevistas |

---

## 11. Arquitectura técnica

### 11.1 Patrón MVC

El sistema se desarrolla siguiendo el patrón **Modelo-Vista-Controlador (MVC)**, separando claramente las responsabilidades:

| Capa | Responsabilidad | Tecnología |
|---|---|---|
| **Modelo (Model)** | Representa los datos y la lógica de negocio. Acceso a la base de datos, validaciones, reglas de negocio (ej: cálculo del semáforo, reglas de aprobación, solapamientos). | PHP (clases de modelo) + MySQL vía PDO |
| **Vista (View)** | Presentación e interfaz de usuario. Renderiza los datos y captura la interacción. En esta PWA, la vista vive principalmente en el cliente. | HTML5, CSS, JavaScript |
| **Controlador (Controller)** | Recibe las peticiones, coordina el modelo y devuelve la respuesta. Expone la API REST que consume el frontend. | PHP (clases de controlador) |

**Principios a respetar:**

- Cada entidad principal (usuario, área de servicio, tarea, pareja ministrante, entrevista, etc.) tiene su modelo, su controlador y sus vistas asociadas.
- La lógica de negocio reside en los modelos, no en los controladores ni en las vistas. Los controladores son delgados: validan la entrada, invocan al modelo y devuelven la respuesta.
- El frontend (vista) se comunica con el backend (controladores) exclusivamente a través de la API REST en formato JSON.
- No se mezcla HTML con lógica de acceso a datos. Las vistas no consultan la base de datos directamente.
- El ruteo dirige cada petición al controlador y método correspondiente.

### 11.2 Estructura de carpetas (referencia MVC)

```
/                       raíz pública
├── index.html          punto de entrada de la PWA
├── manifest.json       configuración PWA
├── service-worker.js   cache offline + push + sincronización
├── /assets             css, js de cliente, íconos
│   ├── /css
│   ├── /js             vistas y lógica de cliente (MVC del lado cliente)
│   └── /icons
├── /app                backend MVC en PHP
│   ├── /controllers    controladores (lógica de coordinación, API REST)
│   ├── /models         modelos (datos, lógica de negocio, acceso a BD)
│   ├── /views          plantillas renderizables del lado servidor (si aplica)
│   ├── /core           router, clase base de modelo/controlador, conexión PDO
│   ├── /config         configuración (BD, OAuth, claves push)
│   └── /helpers        utilidades compartidas
└── /api                endpoints REST (enrutan a los controladores)
```

> La estructura es orientativa. Lo esencial es la separación de responsabilidades MVC, no los nombres exactos de las carpetas.

### 11.3 Arquitectura offline-first

La aplicación debe funcionar **completamente sin conexión a internet**. El usuario nunca debe quedar bloqueado por falta de señal. Cuando se recupera la conexión, los cambios se sincronizan automáticamente.

#### 11.3.1 Principios

- **Offline-first:** la aplicación opera por defecto contra una base de datos local en el dispositivo. La red es una mejora (para sincronizar), no un requisito para usar la app.
- **Todas las operaciones disponibles offline:** crear, editar y ver tareas, subtareas, áreas, parejas ministrantes, entrevistas, asignaciones y agendas funcionan sin conexión.
- **Sincronización automática:** al detectar conexión, la app envía los cambios locales pendientes al servidor y baja los cambios remotos, sin intervención del usuario.

#### 11.3.2 Componentes técnicos

| Componente | Función |
|---|---|
| **Service Worker** | Intercepta las peticiones de red, sirve la app desde cache cuando no hay conexión, gestiona la cola de sincronización y las notificaciones push. |
| **Cache de aplicación (Cache Storage)** | Almacena los archivos estáticos de la PWA (HTML, CSS, JS, íconos) para que la app cargue sin conexión. |
| **Base de datos local (IndexedDB)** | Almacena los datos del usuario en el dispositivo (áreas, tareas, parejas, entrevistas, etc.). Es la fuente de verdad mientras se está offline. |
| **Cola de sincronización (sync queue)** | Registra cada cambio hecho offline (alta, edición, baja) en orden, para reproducirlo contra el servidor cuando vuelva la conexión. Se apoya en la Background Sync API cuando está disponible. |
| **Backend / MySQL** | Fuente de verdad central. Recibe los cambios sincronizados y entrega los cambios de otros usuarios. |

#### 11.3.3 Flujo de sincronización

1. El usuario realiza una acción (ej: registrar una entrevista). 
2. El cambio se aplica de inmediato en IndexedDB y se refleja en la interfaz (respuesta instantánea).
3. El cambio se agrega a la cola de sincronización con una marca de tiempo y un identificador local.
4. **Si hay conexión:** el cambio se envía al servidor y, al confirmarse, se marca como sincronizado.
5. **Si no hay conexión:** el cambio queda en la cola. Al recuperarse la conexión, el service worker procesa la cola en orden contra la API.
6. La app también baja los cambios remotos (de otros usuarios) y actualiza IndexedDB.
7. La interfaz refleja un indicador del estado de sincronización (sincronizado / cambios pendientes / sincronizando).

#### 11.3.4 Identificadores y resolución de conflictos

- Cada registro creado offline recibe un **identificador único generado en el cliente** (ej: UUID), para evitar colisiones al sincronizar con el servidor. El servidor respeta ese identificador o mantiene un mapeo con su propia clave.
- Cada registro lleva una **marca de tiempo de última modificación** (timestamp).
- **Resolución de conflictos: last-write-wins.** Si dos usuarios modifican el mismo registro offline y luego sincronizan, prevalece el cambio con la marca de tiempo más reciente. El cambio anterior se sobrescribe.
- Las operaciones idempotentes (ej: marcar una entrevista como hecha) se diseñan para que reproducir la cola más de una vez no genere duplicados.

#### 11.3.5 Autenticación offline

- El **login con Google requiere conexión a internet la primera vez**. Tras autenticarse, la sesión persiste localmente (token guardado en el dispositivo).
- Con la sesión ya establecida, la aplicación funciona offline indefinidamente hasta que el usuario cierre sesión manualmente.
- No se permite iniciar sesión por primera vez sin conexión.

#### 11.3.6 Consideraciones sobre datos dependientes del servidor

- Las **notificaciones push** requieren conexión para entregarse; los recordatorios generados offline se encolan y se emiten al recuperar la red.
- Las **invitaciones por email** (a usuarios no registrados) requieren conexión, ya que dependen del envío de correo desde el servidor. Si se generan offline, quedan en la cola hasta tener conexión.
- El **semáforo de cumplimiento** se calcula localmente a partir de los datos en IndexedDB, por lo que funciona sin conexión.

### 11.4 Requisitos no funcionales

- **Idioma:** español únicamente en esta versión.
- **Zona horaria:** cada usuario opera en su zona horaria local. El cálculo de trimestres (para el semáforo de ministración) y los recordatorios se basan en la zona horaria del usuario. Se almacena la zona horaria del usuario en su perfil y las fechas se guardan en formato UTC en el servidor, convirtiéndose a local para los cálculos de presentación y semáforo.

### 11.5 Diseño responsive (celular y tablet)

La aplicación está pensada para **celulares y tablets**, en orientación vertical y horizontal. No se optimiza para escritorio en esta versión (aunque al ser web responsive, debe verse razonablemente en pantallas grandes sin romperse).

Se definen dos modos de presentación según el ancho de pantalla, con un punto de quiebre aproximado en 768px:

**Modo celular (ancho < 768px):**
- Navegación principal mediante barra inferior con tres secciones: Áreas, Entrevistas, Avisos.
- Selector de área en el encabezado.
- Vista de una sola columna. Las listas y los detalles son pantallas separadas: se toca un elemento de la lista para abrir su detalle (navegación apilada).

**Modo tablet (ancho ≥ 768px):**
- **Panel lateral fijo** a la izquierda, que reemplaza la barra inferior: contiene la navegación principal (Áreas, Entrevistas, Avisos) y la lista de áreas del usuario siempre visible para cambiar rápido entre ellas.
- **Vistas de dos columnas** donde aporta valor: lista de tareas + detalle de la tarea seleccionada al lado; tablero de parejas + detalle/acciones de la pareja; lista de entrevistas + detalle. Al seleccionar un elemento de la lista, su detalle se muestra en la columna contigua sin cambiar de pantalla.
- Debe funcionar correctamente en orientación vertical y horizontal. En vertical, las dos columnas pueden estrecharse o, si el ancho no alcanza, comportarse como en celular (lista y detalle apilados) manteniendo el panel lateral.

**Principios:**
- Un mismo código base responsive sirve a ambos formatos; no son aplicaciones separadas.
- Los componentes (tarjetas, formularios, tablero) se reorganizan según el ancho disponible, no se rediseñan.
- Los gestos táctiles (tocar, deslizar) y los tamaños mínimos de elementos táctiles se mantienen en ambos formatos.


---

## 12. Modelo de datos

### 12.1 Conceptos de diseño

**Catálogo vs. instancia.** Las áreas de servicio y los llamamientos son un **catálogo** fijo y compartido por todo el sistema (la plantilla: "Presidencia de Estaca", "Obispo", etc.). Pero cada barrio o rama concreto tiene su propia área funcional con sus propias tareas y parejas. Por eso se separan:

- `service_areas` y `callings`: el catálogo (definición, igual para todos).
- `service_area_instances`: el área real de una unidad específica, donde viven las tareas, parejas y miembros.

Así, "Sociedad de Socorro" existe una sola vez como concepto, pero la Sociedad de Socorro del Barrio Centro y la del Barrio Norte son instancias separadas. Esto implementa la regla de que usuarios del mismo barrio comparten el área, pero los de otro barrio no la ven.

**Identificadores.** Las entidades que se crean o editan offline usan **UUID (CHAR(36))** generados en el cliente, para evitar colisiones al sincronizar. Las entidades de catálogo (estacas, unidades, áreas, llamamientos) usan **INT AUTO_INCREMENT** porque se gestionan centralmente.

**Campos de auditoría y sincronización.** Toda tabla con datos editables offline incluye: `created_at`, `updated_at` (timestamps UTC) y `deleted_at` (borrado lógico, NULL si activo). El `updated_at` es la base del last-write-wins.

**Borrado lógico.** Las eliminaciones se hacen con `deleted_at` (soft delete) salvo las que la especificación define como permanentes (ej: eliminación de tarea). El soft delete facilita la sincronización y la resolución de conflictos.

**Zona horaria.** Las fechas/horas se almacenan en UTC. El campo `timezone` del usuario permite convertir a local para el cálculo de trimestres y la presentación.

### 12.2 Tablas

#### users
Usuarios registrados del sistema.

| Campo | Tipo | Notas |
|---|---|---|
| id | CHAR(36) PK | UUID |
| google_id | VARCHAR(255) UNIQUE | Identificador de Google OAuth |
| email | VARCHAR(255) UNIQUE NOT NULL | Identificador único de negocio |
| nombre | VARCHAR(255) NOT NULL | Nombre completo |
| foto_url | VARCHAR(512) NULL | Avatar de Google |
| stake_id | INT FK → stakes.id NOT NULL | Estaca del usuario |
| unit_id | INT FK → units.id NOT NULL | Barrio o rama del usuario |
| timezone | VARCHAR(64) NOT NULL | Ej: "America/Montevideo" |
| created_at | DATETIME | UTC |
| updated_at | DATETIME | UTC |
| deleted_at | DATETIME NULL | Soft delete |

#### sessions
Tokens de sesión persistente (no expiran por tiempo).

| Campo | Tipo | Notas |
|---|---|---|
| id | CHAR(36) PK | UUID |
| user_id | CHAR(36) FK → users.id NOT NULL | |
| token | VARCHAR(255) UNIQUE NOT NULL | Token de sesión (Bearer) |
| device_info | VARCHAR(255) NULL | Para sesiones multi-dispositivo |
| created_at | DATETIME | UTC |
| revoked_at | DATETIME NULL | Cierre de sesión |

#### stakes
Catálogo de estacas y distritos (compartido en todo el sistema).

| Campo | Tipo | Notas |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| tipo | ENUM('estaca','distrito') NOT NULL | Distrito fuera de alcance v1, campo previsto |
| nombre | VARCHAR(255) NOT NULL | |
| created_by | CHAR(36) FK → users.id NULL | Quién la ingresó |
| created_at | DATETIME | UTC |

#### units
Catálogo de barrios y ramas, asociados a una estaca.

| Campo | Tipo | Notas |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| stake_id | INT FK → stakes.id NOT NULL | Estaca/distrito al que pertenece |
| tipo | ENUM('barrio','rama') NOT NULL | |
| nombre | VARCHAR(255) NOT NULL | |
| created_by | CHAR(36) FK → users.id NULL | |
| created_at | DATETIME | UTC |

#### service_areas
Catálogo de áreas de servicio (la plantilla, ver secciones 4 y 5).

| Campo | Tipo | Notas |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| nivel | ENUM('estaca','barrio','rama') NOT NULL | |
| nombre | VARCHAR(255) NOT NULL | Ej: "Presidencia de Estaca", "Cuórum de Élderes" |
| es_solo_solapamiento | BOOLEAN DEFAULT FALSE | TRUE para Consejo de Estaca/Barrio (no se accede directo) |
| orden | INT DEFAULT 0 | Para ordenar en la UI |

#### callings
Catálogo de llamamientos, cada uno dentro de un área de servicio.

| Campo | Tipo | Notas |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| service_area_id | INT FK → service_areas.id NOT NULL | Área a la que pertenece |
| nombre | VARCHAR(255) NOT NULL | Ej: "Presidente", "1er Consejera" |
| es_presidente | BOOLEAN DEFAULT FALSE | Marca el rol con autoridad de aprobación del área |
| es_aprobador_unidad | BOOLEAN DEFAULT FALSE | TRUE para Obispo, Pdte Rama, Pdte Estaca, sus consejeros y sus secretarios |
| created_by | CHAR(36) FK → users.id NULL | NULL si es semilla del sistema |
| created_at | DATETIME | UTC |

#### calling_overlaps
Define qué áreas adicionales recibe un llamamiento por solapamiento (sección 5).

| Campo | Tipo | Notas |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| calling_id | INT FK → callings.id NOT NULL | Llamamiento origen |
| service_area_id | INT FK → service_areas.id NOT NULL | Área adicional que recibe |

#### calling_approvers
Define quién puede aprobar un llamamiento (sección 4.2), cuando no es el presidente del área.

| Campo | Tipo | Notas |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| calling_id | INT FK → callings.id NOT NULL | Llamamiento a aprobar |
| approver_calling_id | INT FK → callings.id NOT NULL | Llamamiento que puede aprobar |

#### service_area_instances
Instancia real de un área de servicio en una unidad concreta. Aquí viven tareas y parejas.

| Campo | Tipo | Notas |
|---|---|---|
| id | CHAR(36) PK | UUID |
| service_area_id | INT FK → service_areas.id NULL | NULL si es área Personal |
| unit_id | INT FK → units.id NULL | Unidad concreta (barrio/rama). NULL si es Personal o de estaca |
| stake_id | INT FK → stakes.id NULL | Para áreas de nivel estaca |
| owner_user_id | CHAR(36) FK → users.id NULL | Dueño del área Personal |
| es_personal | BOOLEAN DEFAULT FALSE | TRUE = área Personal, no eliminable |
| nombre | VARCHAR(255) NOT NULL | Nombre mostrado |
| created_at | DATETIME | UTC |
| updated_at | DATETIME | UTC |
| deleted_at | DATETIME NULL | Soft delete (nunca para Personal) |

#### user_callings
Llamamientos que tiene cada usuario. Un usuario puede tener varios.

| Campo | Tipo | Notas |
|---|---|---|
| id | CHAR(36) PK | UUID |
| user_id | CHAR(36) FK → users.id NOT NULL | |
| calling_id | INT FK → callings.id NOT NULL | |
| unit_id | INT FK → units.id NULL | Unidad donde ejerce (barrio/rama) |
| stake_id | INT FK → stakes.id NULL | Para llamamientos de estaca |
| created_at | DATETIME | UTC |
| deleted_at | DATETIME NULL | Soft delete al quitar el llamamiento |

#### area_members
Membresía y rol de un usuario dentro de una instancia de área. Incluye el estado de acceso (pendiente/activo/rechazado).

| Campo | Tipo | Notas |
|---|---|---|
| id | CHAR(36) PK | UUID |
| area_instance_id | CHAR(36) FK → service_area_instances.id NOT NULL | |
| user_id | CHAR(36) FK → users.id NOT NULL | |
| rol | ENUM('miembro','propietario') NOT NULL DEFAULT 'miembro' | |
| estado | ENUM('pendiente','activo','rechazado') NOT NULL DEFAULT 'pendiente' | Estado de acceso (sección 6) |
| origen | ENUM('llamamiento','solapamiento','invitacion') NOT NULL | Cómo obtuvo el acceso |
| user_calling_id | CHAR(36) FK → user_callings.id NULL | Llamamiento que originó el acceso (si aplica) |
| created_at | DATETIME | UTC |
| updated_at | DATETIME | UTC |
| deleted_at | DATETIME NULL | Soft delete al revocar acceso |

#### service_access_requests
Solicitudes de acceso pendientes de aprobación por un presidente.

| Campo | Tipo | Notas |
|---|---|---|
| id | CHAR(36) PK | UUID |
| user_id | CHAR(36) FK → users.id NOT NULL | Solicitante |
| area_instance_id | CHAR(36) FK → service_area_instances.id NOT NULL | |
| user_calling_id | CHAR(36) FK → user_callings.id NOT NULL | Llamamiento que disparó la solicitud |
| estado | ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente' | |
| resolved_by | CHAR(36) FK → users.id NULL | Quién aprobó/rechazó |
| created_at | DATETIME | UTC |
| resolved_at | DATETIME NULL | |

#### tasks
Tareas dentro de una instancia de área.

| Campo | Tipo | Notas |
|---|---|---|
| id | CHAR(36) PK | UUID |
| area_instance_id | CHAR(36) FK → service_area_instances.id NOT NULL | Área a la que pertenece |
| creador_id | CHAR(36) FK → users.id NOT NULL | Quién la creó |
| responsable_id | CHAR(36) FK → users.id NULL | Responsable (NULL = sin responsable) |
| titulo | VARCHAR(255) NOT NULL | Obligatorio |
| descripcion | TEXT NULL | |
| fecha_inicio | DATE NULL | |
| fecha_vencimiento | DATE NULL | |
| estado | ENUM('pendiente','en_progreso','completada') DEFAULT 'pendiente' | |
| created_at | DATETIME | UTC |
| updated_at | DATETIME | UTC |
| deleted_at | DATETIME NULL | Eliminación (permanente según CU-10, pero soft para sync) |

#### subtasks
Subtareas de una tarea (un solo nivel).

| Campo | Tipo | Notas |
|---|---|---|
| id | CHAR(36) PK | UUID |
| task_id | CHAR(36) FK → tasks.id NOT NULL | Tarea padre |
| titulo | VARCHAR(255) NOT NULL | Obligatorio |
| descripcion | TEXT NULL | |
| fecha_inicio | DATE NULL | |
| fecha_fin | DATE NULL | |
| estado | ENUM('pendiente','en_progreso','completada') DEFAULT 'pendiente' | |
| created_at | DATETIME | UTC |
| updated_at | DATETIME | UTC |
| deleted_at | DATETIME NULL | |

#### ministering_pairs
Parejas ministrantes de un Cuórum de Élderes o Sociedad de Socorro.

| Campo | Tipo | Notas |
|---|---|---|
| id | CHAR(36) PK | UUID |
| area_instance_id | CHAR(36) FK → service_area_instances.id NOT NULL | Cuórum o SdS |
| integrante_1 | VARCHAR(255) NOT NULL | Texto libre (no usuario) |
| integrante_2 | VARCHAR(255) NOT NULL | Texto libre (no usuario) |
| responsable_id | CHAR(36) FK → users.id NULL | Entrevistador responsable (usuario de la presidencia). Persistente entre trimestres: solo cambia por reasignación explícita |
| created_at | DATETIME | UTC |
| updated_at | DATETIME | UTC |
| deleted_at | DATETIME NULL | Soft delete |

#### ministering_interviews
Histórico completo de entrevistas de ministración. Incluye agenda y realización.

| Campo | Tipo | Notas |
|---|---|---|
| id | CHAR(36) PK | UUID |
| pair_id | CHAR(36) FK → ministering_pairs.id NOT NULL | Pareja entrevistada |
| estado | ENUM('agendada','realizada') NOT NULL | |
| agendada_para | DATETIME NULL | Fecha/hora de la cita (UTC) |
| agendada_por | CHAR(36) FK → users.id NULL | Quién agendó |
| fecha_realizada | DATE NULL | Fecha en que se realizó |
| trimestre | CHAR(7) NULL | Trimestre calculado, ej: "2026-Q2", para el comparativo |
| created_at | DATETIME | UTC |
| updated_at | DATETIME | UTC |
| deleted_at | DATETIME NULL | |

> El responsable de la entrevista no se almacena por entrevista: se asume siempre el `responsable_id` de la pareja, según la regla de negocio (CU-23 R4).

#### leadership_interviews
Entrevistas de liderazgo (Tipo A): Obispado, Presidencia de Rama, Presidencia de Estaca.

| Campo | Tipo | Notas |
|---|---|---|
| id | CHAR(36) PK | UUID |
| area_instance_id | CHAR(36) FK → service_area_instances.id NOT NULL | Área de liderazgo |
| creador_id | CHAR(36) FK → users.id NOT NULL | Líder o secretario que la cargó |
| persona | VARCHAR(255) NOT NULL | A quién entrevistar (texto libre, sin cuenta) |
| motivo | VARCHAR(255) NULL | Recomendación, llamamiento, seguimiento, etc. |
| fecha_objetivo | DATETIME NULL | Fecha/hora objetivo o agendada (UTC) |
| estado | ENUM('pendiente','realizada') NOT NULL DEFAULT 'pendiente' | |
| created_at | DATETIME | UTC |
| updated_at | DATETIME | UTC |
| deleted_at | DATETIME NULL | |

#### invitations
Invitaciones para compartir un área (a usuarios existentes o no registrados).

| Campo | Tipo | Notas |
|---|---|---|
| id | CHAR(36) PK | UUID |
| area_instance_id | CHAR(36) FK → service_area_instances.id NOT NULL | Área a la que se invita |
| invitado_por | CHAR(36) FK → users.id NOT NULL | Propietario que invitó |
| email | VARCHAR(255) NOT NULL | Email del invitado |
| invited_user_id | CHAR(36) FK → users.id NULL | Si el email ya era usuario |
| token | VARCHAR(255) UNIQUE NULL | Token para invitación por email (no registrado) |
| estado | ENUM('pendiente','aceptada','rechazada','expirada') NOT NULL DEFAULT 'pendiente' | |
| expira_en | DATETIME NULL | 7 días para invitaciones por email |
| created_at | DATETIME | UTC |
| resolved_at | DATETIME NULL | |

#### notifications
Notificaciones in-app (zona de notificaciones) y registro de las enviadas por push.

| Campo | Tipo | Notas |
|---|---|---|
| id | CHAR(36) PK | UUID |
| user_id | CHAR(36) FK → users.id NOT NULL | Destinatario |
| tipo | VARCHAR(64) NOT NULL | Ej: 'invitacion', 'solicitud_acceso', 'recordatorio_entrevista', 'cita_proxima', 'cita_vencida' |
| titulo | VARCHAR(255) NOT NULL | |
| cuerpo | TEXT NULL | |
| data | JSON NULL | Datos asociados (ids para enlazar a la entidad) |
| leida | BOOLEAN DEFAULT FALSE | |
| created_at | DATETIME | UTC |

#### push_subscriptions
Suscripciones Web Push por dispositivo.

| Campo | Tipo | Notas |
|---|---|---|
| id | CHAR(36) PK | UUID |
| user_id | CHAR(36) FK → users.id NOT NULL | |
| endpoint | VARCHAR(512) NOT NULL | Endpoint del navegador |
| p256dh | VARCHAR(255) NOT NULL | Clave pública del cliente |
| auth | VARCHAR(255) NOT NULL | Secreto de autenticación |
| created_at | DATETIME | UTC |
| deleted_at | DATETIME NULL | Al desuscribir |

#### sync_queue
Cola de sincronización del lado servidor (registro de cambios para reconciliación). En el cliente existe su equivalente en IndexedDB.

| Campo | Tipo | Notas |
|---|---|---|
| id | CHAR(36) PK | UUID del cambio (generado en cliente) |
| user_id | CHAR(36) FK → users.id NOT NULL | Quién originó el cambio |
| entidad | VARCHAR(64) NOT NULL | Tabla afectada |
| entidad_id | CHAR(36) NOT NULL | Id del registro afectado |
| operacion | ENUM('crear','editar','eliminar') NOT NULL | |
| payload | JSON NULL | Datos del cambio |
| client_timestamp | DATETIME NOT NULL | Marca de tiempo del cliente (para last-write-wins) |
| server_timestamp | DATETIME NULL | Cuándo se aplicó en el servidor |
| estado | ENUM('pendiente','aplicado','conflicto') DEFAULT 'pendiente' | |

> Nota: la tabla `audit_log` (registro de acciones críticas) se define en la sección 16.8, dentro de Seguridad.

### 12.3 Índices recomendados

- `users`: índice único en `email` y `google_id`; índice en `(stake_id, unit_id)`.
- `units`: índice en `stake_id`.
- `callings`: índice en `service_area_id`.
- `service_area_instances`: índice en `(service_area_id, unit_id)` y en `owner_user_id`.
- `area_members`: índice en `(area_instance_id, user_id)`, índice en `user_id`, índice en `(area_instance_id, estado)`.
- `tasks`: índice en `area_instance_id`, en `responsable_id` y en `(area_instance_id, deleted_at)`.
- `subtasks`: índice en `task_id`.
- `ministering_pairs`: índice en `area_instance_id` y en `responsable_id`.
- `ministering_interviews`: índice en `(pair_id, trimestre)` y en `estado`.
- `leadership_interviews`: índice en `(area_instance_id, estado)`.
- `invitations`: índice en `email`, índice único en `token`, índice en `(area_instance_id, estado)`.
- `notifications`: índice en `(user_id, leida)`.
- `sync_queue`: índice en `(user_id, estado)`, índice en `(entidad, entidad_id)`.

### 12.4 Relaciones clave (resumen)

- Un `user` pertenece a una `stake` y una `unit`, y tiene muchos `user_callings`.
- Un `calling` pertenece a una `service_area`; sus solapamientos y aprobadores se definen en `calling_overlaps` y `calling_approvers`.
- Una `service_area_instance` representa un área real de una unidad; agrupa `area_members`, `tasks`, `ministering_pairs` y `leadership_interviews`.
- Una `ministering_pair` tiene un `responsable_id` (usuario) y muchas `ministering_interviews` (histórico).
- Cada cambio offline se refleja en `sync_queue` para la reconciliación con resolución last-write-wins por `client_timestamp`.


---

## 13. API REST

### 13.1 Convenciones generales

- **Base URL:** `/api`
- **Formato:** JSON en request y response. Header `Content-Type: application/json`.
- **Autenticación:** todas las rutas (salvo las marcadas como públicas) requieren el header `Authorization: Bearer {token}`. El token se obtiene al autenticarse y se valida contra la tabla `sessions`.
- **Identificadores:** los recursos editables usan UUID generados en cliente; el cliente puede enviar el `id` al crear, para soportar offline.
- **Fechas:** se envían y reciben en formato ISO 8601 UTC (ej: `2026-06-15T18:00:00Z`).
- **Soft delete:** las eliminaciones marcan `deleted_at`; la respuesta confirma la operación.

#### Códigos de estado HTTP

| Código | Significado |
|---|---|
| 200 OK | Operación exitosa (GET, PUT) |
| 201 Created | Recurso creado (POST) |
| 204 No Content | Operación exitosa sin cuerpo (DELETE) |
| 400 Bad Request | Datos inválidos o faltantes |
| 401 Unauthorized | Token ausente o inválido |
| 403 Forbidden | Sin permisos para la operación |
| 404 Not Found | Recurso inexistente |
| 409 Conflict | Conflicto (ej: invitación duplicada) |
| 422 Unprocessable Entity | Validación de negocio fallida |
| 500 Internal Server Error | Error del servidor |

#### Formato de error estándar

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "El título es obligatorio.",
    "fields": { "titulo": "obligatorio" }
  }
}
```

#### Formato de respuesta exitosa

```json
{
  "data": { },
  "meta": { }
}
```

### 13.2 Autenticación

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| POST | `/api/auth/google` | Pública | Intercambia el token de Google por una sesión. Crea el usuario si no existe. |
| POST | `/api/auth/logout` | Bearer | Invalida el token de sesión actual. |
| GET | `/api/auth/me` | Bearer | Devuelve el usuario autenticado y su perfil. |

**POST /api/auth/google**
```
Request:  { "google_token": "...", "timezone": "America/Montevideo" }
Response 200 (existente): { "data": { "token": "...", "user": {...}, "perfil_completo": true } }
Response 201 (nuevo):     { "data": { "token": "...", "user": {...}, "perfil_completo": false } }
```
> Si `perfil_completo` es false, el cliente redirige al formulario de perfil eclesiastico (CU-05).

### 13.3 Perfil y catálogos

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| GET | `/api/profile` | Bearer | Perfil eclesiastico del usuario (estaca, unidad, llamamientos). |
| PUT | `/api/profile` | Bearer | Actualiza estaca/unidad del usuario (CU-06). |
| GET | `/api/stakes` | Bearer | Lista de estacas/distritos (catálogo). |
| POST | `/api/stakes` | Bearer | Crea una estaca/distrito nueva. |
| GET | `/api/stakes/{id}/units` | Bearer | Unidades (barrios/ramas) de una estaca. |
| POST | `/api/stakes/{id}/units` | Bearer | Crea un barrio/rama en la estaca. |
| GET | `/api/service-areas?nivel=` | Bearer | Áreas de servicio del catálogo, filtrables por nivel. |
| GET | `/api/service-areas/{id}/callings` | Bearer | Llamamientos de un área de servicio. |
| POST | `/api/service-areas/{id}/callings` | Bearer | Crea un llamamiento manual en el área. |

**POST /api/stakes**
```
Request:  { "tipo": "estaca", "nombre": "Estaca Montevideo Norte" }
Response 201: { "data": { "id": 12, "tipo": "estaca", "nombre": "Estaca Montevideo Norte" } }
```

### 13.4 Llamamientos del usuario

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| GET | `/api/user-callings` | Bearer | Llamamientos del usuario autenticado. |
| POST | `/api/user-callings` | Bearer | Agrega un llamamiento y genera solicitudes de acceso (CU-19). |
| DELETE | `/api/user-callings/{id}` | Bearer | Quita un llamamiento y gestiona el impacto en áreas (CU-21). |

**POST /api/user-callings**
```
Request:  { "id": "uuid-cliente", "calling_id": 45, "unit_id": 8, "stake_id": null }
Response 201: {
  "data": {
    "user_calling": {...},
    "accesos_creados": [
      { "area_instance_id": "...", "nombre": "Cuórum de Élderes", "estado": "pendiente" },
      { "area_instance_id": "...", "nombre": "Consejo de Barrio", "estado": "pendiente", "origen": "solapamiento" }
    ]
  }
}
```

### 13.5 Áreas de servicio (instancias)

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| GET | `/api/areas` | Bearer | Áreas del usuario (Personal + activas + pendientes), para el dashboard (CU-07). |
| GET | `/api/areas/{id}` | Bearer | Detalle de un área con sus miembros. Requiere acceso activo. |
| DELETE | `/api/areas/{id}` | Bearer (propietario) | Elimina el área; migra tareas a Personal (CU-18). |
| GET | `/api/areas/{id}/members` | Bearer (miembro) | Lista de miembros del área. |
| PUT | `/api/areas/{id}/members/{userId}/role` | Bearer (propietario) | Promueve a propietario (CU-16). |
| DELETE | `/api/areas/{id}/members/{userId}` | Bearer (propietario) | Remueve un miembro; sus tareas quedan sin responsable (CU-17). |

> No existe endpoint de creación manual de áreas eclesiasticas: se generan automáticamente al registrar llamamientos. El área Personal se crea en el registro.

### 13.6 Solicitudes de acceso

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| GET | `/api/access-requests` | Bearer | Solicitudes pendientes que el usuario puede aprobar (panel del presidente). |
| POST | `/api/access-requests/{id}/approve` | Bearer (aprobador) | Aprueba una solicitud (CU-20). |
| POST | `/api/access-requests/{id}/reject` | Bearer (aprobador) | Rechaza una solicitud (CU-20). |

### 13.7 Tareas y subtareas

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| GET | `/api/areas/{id}/tasks` | Bearer (miembro activo) | Tareas del área (CU-12). |
| POST | `/api/areas/{id}/tasks` | Bearer (miembro activo) | Crea una tarea (CU-08). |
| GET | `/api/tasks/{id}` | Bearer (miembro activo) | Detalle de tarea con subtareas. |
| PUT | `/api/tasks/{id}` | Bearer (creador o propietario) | Edita una tarea (CU-09). |
| DELETE | `/api/tasks/{id}` | Bearer (creador o propietario) | Elimina tarea y subtareas (CU-10). |
| POST | `/api/tasks/{id}/subtasks` | Bearer (miembro activo) | Crea una subtarea (CU-11). |
| PUT | `/api/subtasks/{id}` | Bearer (creador o propietario) | Edita una subtarea. |
| DELETE | `/api/subtasks/{id}` | Bearer (creador o propietario) | Elimina una subtarea. |

**POST /api/areas/{id}/tasks**
```
Request:  {
  "id": "uuid-cliente",
  "titulo": "Coordinar actividad de servicio",
  "descripcion": "...",
  "fecha_inicio": "2026-06-01",
  "fecha_vencimiento": "2026-06-15",
  "responsable_id": "uuid-usuario"
}
Response 201: { "data": { ...tarea creada... } }
```

### 13.8 Parejas ministrantes y entrevistas (Tipo B)

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| GET | `/api/areas/{id}/pairs` | Bearer (presidencia/secretario) | Parejas del Cuórum/SdS con su estado de semáforo. |
| POST | `/api/areas/{id}/pairs` | Bearer (presidencia/secretario) | Registra una pareja (CU-22). |
| PUT | `/api/pairs/{id}` | Bearer (presidencia/secretario) | Edita una pareja. |
| DELETE | `/api/pairs/{id}` | Bearer (presidencia/secretario) | Elimina una pareja. |
| PUT | `/api/pairs/{id}/assign` | Bearer (presidencia/secretario) | Asigna entrevistador responsable (CU-28). |
| POST | `/api/pairs/{id}/schedule` | Bearer (presidencia/secretario) | Agenda fecha y hora de entrevista (CU-29). |
| POST | `/api/pairs/{id}/interview` | Bearer (presidencia/secretario) | Registra entrevista realizada (CU-23). |
| GET | `/api/areas/{id}/compliance` | Bearer (presidencia/secretario/supervisor) | Tablero de cumplimiento con histórico (CU-24). |

**GET /api/areas/{id}/compliance**
```
Response 200: {
  "data": {
    "trimestre_actual": {
      "etiqueta": "2026-Q2",
      "mes_del_trimestre": 3,
      "total": 8, "al_dia": 5, "porcentaje": 63,
      "parejas": [
        { "id": "...", "integrante_1": "Juan Pérez", "integrante_2": "Andrés Gómez",
          "responsable": { "id": "...", "nombre": "..." },
          "estado": "verde", "fecha_realizada": "2026-04-12", "agendada_para": null }
      ]
    },
    "trimestre_anterior": { "etiqueta": "2026-Q1", "total": 8, "al_dia": 6, "porcentaje": 75 }
  }
}
```

**POST /api/pairs/{id}/interview**
```
Request:  { "id": "uuid-cliente", "fecha_realizada": "2026-06-20" }   (fecha opcional, default hoy)
Response 201: { "data": { "estado_pareja": "verde", "trimestre": "2026-Q2" } }
```

**POST /api/pairs/{id}/schedule**
```
Request:  { "id": "uuid-cliente", "agendada_para": "2026-06-15T18:00:00Z" }
Response 201: { "data": { "estado_pareja": "agendada", "agendada_para": "2026-06-15T18:00:00Z" } }
```

### 13.9 Entrevistas de liderazgo (Tipo A)

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| GET | `/api/areas/{id}/leadership-interviews` | Bearer (líder/secretario) | Lista de entrevistas pendientes y realizadas. |
| POST | `/api/areas/{id}/leadership-interviews` | Bearer (líder/secretario) | Crea entrevista de liderazgo (CU-25). |
| PUT | `/api/leadership-interviews/{id}` | Bearer (líder/secretario) | Edita una entrevista. |
| POST | `/api/leadership-interviews/{id}/complete` | Bearer (líder/secretario) | Marca como realizada (CU-26). |
| DELETE | `/api/leadership-interviews/{id}` | Bearer (líder/secretario) | Elimina una entrevista. |

### 13.10 Invitaciones

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| POST | `/api/areas/{id}/invitations` | Bearer (propietario) | Invita por email a un área (CU-13, CU-14). |
| GET | `/api/invitations` | Bearer | Invitaciones pendientes del usuario. |
| POST | `/api/invitations/{id}/accept` | Bearer | Acepta una invitación (CU-15). |
| POST | `/api/invitations/{id}/reject` | Bearer | Rechaza una invitación (CU-15). |
| GET | `/api/invitations/token/{token}` | Pública | Valida un token de invitación por email (CU-14). |

**POST /api/areas/{id}/invitations**
```
Request:  { "email": "persona@ejemplo.com" }
Response 201 (usuario existente): { "data": { "estado": "pendiente", "tipo": "push" } }
Response 201 (no registrado):     { "data": { "estado": "pendiente", "tipo": "email" } }
Response 409: { "error": { "code": "ALREADY_MEMBER", "message": "Este usuario ya pertenece al área." } }
```

### 13.11 Notificaciones y push

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| GET | `/api/notifications` | Bearer | Notificaciones in-app del usuario. |
| POST | `/api/notifications/{id}/read` | Bearer | Marca una notificación como leída. |
| POST | `/api/push/subscribe` | Bearer | Registra una suscripción Web Push. |
| DELETE | `/api/push/subscribe` | Bearer | Elimina la suscripción del dispositivo. |

### 13.12 Sincronización

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| POST | `/api/sync/push` | Bearer | Envía la cola de cambios locales para aplicar en el servidor. |
| GET | `/api/sync/pull?since=` | Bearer | Baja los cambios remotos desde un timestamp dado. |

**POST /api/sync/push**
```
Request:  {
  "changes": [
    { "id": "uuid-cambio", "entidad": "tasks", "entidad_id": "uuid", "operacion": "crear",
      "payload": {...}, "client_timestamp": "2026-05-31T14:30:00Z" }
  ]
}
Response 200: {
  "data": {
    "aplicados": ["uuid-cambio"],
    "conflictos": [
      { "id": "uuid-cambio-2", "entidad_id": "uuid", "resolucion": "server_gana",
        "registro_servidor": {...} }
    ]
  }
}
```
> La resolución de conflictos es last-write-wins por `client_timestamp`. Cuando el servidor tiene una versión más reciente, devuelve `server_gana` con el registro vigente para que el cliente actualice.

**GET /api/sync/pull?since={timestamp}**
```
Response 200: {
  "data": {
    "changes": [
      { "entidad": "tasks", "entidad_id": "uuid", "operacion": "editar", "payload": {...},
        "server_timestamp": "2026-05-31T15:00:00Z" }
    ],
    "server_time": "2026-05-31T15:05:00Z"
  }
}
```
> El cliente guarda `server_time` y lo usa como `since` en la próxima sincronización.


---

## 14. Esquema de IndexedDB (almacenamiento cliente)

La base de datos local del dispositivo replica las entidades necesarias para operar offline. Es la fuente de verdad mientras no hay conexión. Se sincroniza con el servidor mediante la cola de cambios (ver sección 13.12).

### 14.1 Object stores

Cada object store usa el `id` (UUID o INT del servidor) como keyPath. Los stores reflejan las tablas del servidor que el cliente necesita.

| Object store | keyPath | Índices | Notas |
|---|---|---|---|
| `users` | id | email | Datos del usuario autenticado y de otros usuarios referenciados (responsables, miembros). |
| `stakes` | id | — | Catálogo cacheado. |
| `units` | id | stake_id | Catálogo cacheado. |
| `service_areas` | id | nivel | Catálogo cacheado. |
| `callings` | id | service_area_id | Catálogo cacheado. |
| `user_callings` | id | user_id | Llamamientos del usuario. |
| `service_area_instances` | id | unit_id, owner_user_id | Áreas a las que pertenece el usuario. |
| `area_members` | id | area_instance_id, user_id | Membresías de las áreas del usuario. |
| `tasks` | id | area_instance_id, responsable_id | Tareas de las áreas del usuario. |
| `subtasks` | id | task_id | Subtareas. |
| `ministering_pairs` | id | area_instance_id, responsable_id | Parejas de las áreas que gestiona. |
| `ministering_interviews` | id | pair_id | Histórico de entrevistas. |
| `leadership_interviews` | id | area_instance_id | Entrevistas de liderazgo. |
| `invitations` | id | — | Invitaciones del usuario. |
| `notifications` | id | leida | Notificaciones in-app. |
| `sync_queue` | id | estado | Cola local de cambios pendientes. |
| `meta` | clave | — | Pares clave/valor: token de sesión, último `since` de sync, etc. |

### 14.2 Estructura de la cola de sincronización local (`sync_queue`)

Cada cambio que el usuario realiza offline genera un registro en este store:

```json
{
  "id": "uuid-del-cambio",
  "entidad": "tasks",
  "entidad_id": "uuid-del-registro",
  "operacion": "crear",
  "payload": { },
  "client_timestamp": "2026-05-31T14:30:00Z",
  "estado": "pendiente",
  "intentos": 0
}
```

- `estado`: `pendiente` | `enviando` | `aplicado` | `conflicto`.
- `intentos`: contador de reintentos de envío.
- Los cambios se procesan en orden de `client_timestamp`.

### 14.3 Estrategia de cache (Service Worker)

| Recurso | Estrategia |
|---|---|
| App shell (HTML, CSS, JS, íconos) | Cache-first. Se precachean en la instalación del service worker. |
| Llamadas a la API (GET de datos) | Network-first con fallback a IndexedDB. Si hay red, actualiza; si no, sirve lo local. |
| Operaciones de escritura (POST/PUT/DELETE) | Se aplican primero en IndexedDB y se encolan; se envían cuando hay red (Background Sync). |
| Catálogos (estacas, áreas, llamamientos) | Stale-while-revalidate: sirve cache y actualiza en segundo plano. |

### 14.4 Flujo de arranque del cliente

1. Al abrir la app, el service worker sirve el app shell desde cache (carga instantánea, online u offline).
2. La app lee la sesión desde `meta`. Si no hay token, va a login (requiere red).
3. La app renderiza desde IndexedDB de inmediato.
4. Si hay red: dispara `sync/pull` (con el último `since`) y procesa la `sync_queue` con `sync/push`.
5. La interfaz refleja el estado de sincronización (sincronizado / pendiente / sincronizando).

---

## 15. Matriz de autorización

Esta matriz consolida quién puede ejecutar cada operación. El backend valida estos permisos en cada endpoint; nunca confía en el cliente. "Acceso activo" significa ser `area_member` con `estado = activo`.

### 15.1 Tareas y subtareas

| Operación | Quién |
|---|---|
| Ver tareas de un área | Cualquier miembro con acceso activo al área |
| Crear tarea / subtarea | Cualquier miembro con acceso activo al área |
| Editar / eliminar tarea | El creador de la tarea o un propietario del área |
| Editar / eliminar subtarea | El creador de la tarea padre o un propietario del área |

### 15.2 Áreas de servicio

| Operación | Quién |
|---|---|
| Ver dashboard de áreas propias | Cualquier usuario (sus propias áreas) |
| Entrar a un área | Miembro con acceso activo |
| Eliminar área | Propietario del área (nunca el área Personal) |
| Ver miembros | Miembro con acceso activo |
| Promover a propietario | Propietario del área |
| Remover miembro | Propietario del área (no a sí mismo si es el único propietario) |
| Invitar a un área | Propietario del área |

### 15.3 Llamamientos y acceso

| Operación | Quién |
|---|---|
| Agregar/quitar llamamiento propio | El propio usuario |
| Aprobar/rechazar solicitud de acceso | Según reglas de la sección 4.2: el presidente del área correspondiente, o el aprobador de unidad (Obispo/Pdte Rama/Pdte Estaca y sus consejeros). Auto-aprobado si no hay autoridad registrada. |

### 15.4 Parejas ministrantes y entrevistas (Tipo B)

| Operación | Quién |
|---|---|
| Gestionar parejas (crear/editar/eliminar/asignar/agendar/registrar) del propio Cuórum de Élderes | Presidente, consejeros y secretarios del Cuórum de Élderes |
| Gestionar parejas de la propia Sociedad de Socorro (barrio/rama) | Presidenta, consejeras y secretarias de la Sociedad de Socorro |
| Gestionar parejas de Cuórum de Élderes y Sociedad de Socorro de barrios/ramas de la estaca | Presidente, consejeros y secretarios de la Presidencia de Estaca |
| Gestionar parejas de las Sociedades de Socorro de barrios/ramas de la estaca | Presidenta, consejeras y secretarias de la Sociedad de Socorro de Estaca |
| Asignar entrevistador responsable | Los mismos roles de gestión; el responsable debe ser de la presidencia (presidente/a o consejero/a), no secretario |
| Ver tablero de cumplimiento (detalle por barrio/rama) | Presidencia/secretarios del área; vista supervisora para Presidencia de Estaca y Sociedad de Socorro de Estaca |

### 15.5 Entrevistas de liderazgo (Tipo A)

| Operación | Quién |
|---|---|
| Crear/editar/completar/eliminar entrevista de liderazgo | Líderes (Obispado, Presidencia de Rama, Presidencia de Estaca) y sus secretarios, dentro de su propia área |

### 15.6 Notas de implementación

- La autorización para parejas y entrevistas se resuelve verificando los `user_callings` activos del usuario contra el área de la operación.
- Para la vista supervisora de estaca, el backend resuelve las instancias de área de todos los barrios/ramas de la estaca del usuario.
- Toda operación de escritura valida además que el área no esté eliminada y que el usuario tenga acceso activo (salvo las acciones de aprobación, que dependen del llamamiento, no de la membresía).


---

## 16. Seguridad y privacidad

El sistema maneja información sensible (datos eclesiasticos de personas, parejas ministrantes, entrevistas). La prevención de filtración de información es un requisito central, tanto técnico como de confianza. Esta sección define los controles obligatorios.

### 16.1 Principios

- **Mínimo privilegio:** cada usuario accede únicamente a los datos de las áreas a las que pertenece con acceso activo. Nunca a datos de áreas ajenas, aunque comparta otra área con el mismo usuario (ver regla de negocio 12).
- **Defensa en profundidad:** la seguridad se valida en el servidor en cada operación; el cliente nunca es la única barrera.
- **No confiar en el cliente:** todo permiso, filtro y validación se reaplica en el backend. El frontend oculta opciones por usabilidad, pero el backend es quien autoriza.
- **Privacidad por diseño:** las personas entrevistadas no son usuarios y no deben poder ser identificadas ni contactadas por el sistema. Los datos de ministración son visibles solo para los roles autorizados (sección 15.4).

### 16.2 Transporte

- **HTTPS obligatorio** en toda la aplicación. Sin excepciones. Redirección forzada de HTTP a HTTPS.
- Cabeceras de seguridad: `Strict-Transport-Security` (HSTS), `Content-Security-Policy`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`.
- Cookies (si se usan) con flags `Secure`, `HttpOnly` y `SameSite=Strict`.

### 16.3 Autenticación y sesión

- Autenticación mediante Google OAuth 2.0. El backend **verifica el token de Google** contra los servidores de Google antes de crear la sesión (valida firma, emisor, audiencia y expiración). Nunca confía en datos del token sin verificar.
- El token de sesión propio es un valor aleatorio de alta entropía (mínimo 256 bits), almacenado **hasheado** en la tabla `sessions` (no en texto plano).
- La sesión persiste hasta cierre manual, pero el backend puede invalidar tokens (revocación) ante actividad sospechosa.
- Protección contra fuerza bruta y abuso: límite de intentos y rate limiting por IP y por usuario en endpoints sensibles (login, sincronización, invitaciones).

### 16.4 Autorización

- Cada endpoint valida la autorización según la matriz de la sección 15, en el servidor, en cada petición.
- Las consultas a la base de datos siempre filtran por las áreas a las que el usuario tiene acceso activo. No se devuelven registros de áreas ajenas bajo ninguna circunstancia.
- Prevención de **IDOR (Insecure Direct Object Reference):** acceder a `/api/tasks/{id}` de un área ajena devuelve 404 (no 403), para no revelar la existencia del recurso.
- La verificación de permisos para parejas y entrevistas se resuelve contra los `user_callings` activos del usuario, nunca contra parámetros enviados por el cliente.

### 16.5 Cifrado de datos en reposo

**En el servidor (MySQL):**

- Los campos sensibles (nombres de personas en parejas ministrantes, motivos de entrevistas, datos de personas entrevistadas) se almacenan **cifrados** a nivel de aplicación (cifrado simétrico autenticado, ej: AES-256-GCM), con las claves gestionadas fuera de la base de datos (variables de entorno o gestor de secretos del hosting).
- Las copias de seguridad de la base heredan el cifrado de los campos sensibles.

**En el cliente (IndexedDB):**

- Los datos sensibles almacenados localmente para uso offline se guardan **cifrados** en IndexedDB. No se almacenan en texto plano.
- La clave de cifrado del cliente se deriva de la sesión del usuario y se mantiene en memoria durante la sesión activa; no se persiste en texto plano en el dispositivo.

> **Tensión de diseño a resolver (cifrado cliente + offline + push):** el cifrado del lado cliente protege los datos si el dispositivo se pierde o es accedido por terceros. Pero como la app debe funcionar offline tras cerrar y reabrir, la clave debe poder reconstruirse sin conexión. La estrategia recomendada: derivar la clave de un secreto ligado a la sesión persistente del dispositivo (almacenado de forma protegida, ej: en una entrada no exportable cuando la plataforma lo permita), de modo que solo la sesión válida pueda descifrar. Al cerrar sesión, la clave y los datos se destruyen (ver 16.7). Este punto debe validarse técnicamente en la fase de implementación según las capacidades del navegador objetivo.

### 16.6 Validación y saneamiento de datos

- **Validación en servidor de toda entrada:** tipos, longitudes, formatos, obligatoriedad. La validación de cliente es solo para experiencia de usuario.
- **Consultas parametrizadas (PDO prepared statements)** en todo acceso a base de datos. Prohibido construir SQL por concatenación de strings. Previene inyección SQL.
- **Escape de salida** en todo dato mostrado en la interfaz para prevenir XSS. El contenido generado por usuarios (títulos, descripciones, nombres) se trata como no confiable.
- **Validación de tipos de dato en sincronización:** los cambios entrantes por `sync/push` se validan igual que cualquier otra escritura, incluyendo permisos. Un cliente comprometido no puede inyectar datos en áreas ajenas.

### 16.7 Datos en el dispositivo

- **Borrado al cerrar sesión:** al cerrar sesión, se eliminan todos los datos locales (IndexedDB y caches de datos) y la clave de cifrado en memoria. El dispositivo queda sin información sensible.
- Antes de cerrar sesión, el sistema verifica que no queden cambios sin sincronizar; si los hay, advierte al usuario (para no perder datos no enviados).
- Las notificaciones push no incluyen datos sensibles en su payload; transportan identificadores para que la app obtenga el detalle ya autenticada.

### 16.8 Auditoría

- Se registra un **log de auditoría de acciones críticas:** eliminación de áreas, eliminación de tareas, cambios de permisos (promover a propietario, remover miembro), aprobación/rechazo de accesos.
- Cada registro de auditoría guarda: quién (user_id), qué acción, sobre qué recurso, cuándo (timestamp UTC) y desde qué sesión.
- El log de auditoría es de solo lectura para la aplicación (no editable ni borrable desde la API).
- No se audita la simple visualización de datos en esta versión (solo acciones críticas, según definición).

#### Tabla audit_log (agregar al modelo de datos)

| Campo | Tipo | Notas |
|---|---|---|
| id | CHAR(36) PK | UUID |
| user_id | CHAR(36) FK → users.id NOT NULL | Quién ejecutó la acción |
| accion | VARCHAR(64) NOT NULL | Ej: 'eliminar_area', 'eliminar_tarea', 'promover_propietario', 'remover_miembro', 'aprobar_acceso', 'rechazar_acceso' |
| entidad | VARCHAR(64) NOT NULL | Tabla/recurso afectado |
| entidad_id | CHAR(36) NOT NULL | Id del recurso |
| detalle | JSON NULL | Contexto adicional |
| session_id | CHAR(36) FK → sessions.id NULL | Sesión desde la que se actuó |
| created_at | DATETIME NOT NULL | UTC |

### 16.9 Gestión de secretos y configuración

- Las credenciales (clave de BD, secretos de OAuth, claves VAPID de push, claves de cifrado) **nunca** se versionan en el código ni se exponen al cliente. Se gestionan por variables de entorno o el gestor de secretos del hosting.
- Las claves VAPID privadas y la clave de cifrado de servidor residen solo en el backend.
- Rotación de claves prevista: el diseño contempla poder rotar la clave de cifrado sin perder acceso a los datos (versionado de clave por registro si se implementa rotación).

### 16.10 Resumen de amenazas mitigadas

| Amenaza | Mitigación |
|---|---|
| Intercepción en tránsito | HTTPS obligatorio + HSTS |
| Acceso a datos de áreas ajenas | Filtrado por acceso activo en servidor + IDOR devuelve 404 |
| Inyección SQL | Consultas parametrizadas (PDO) |
| XSS | Escape de salida + CSP |
| Robo de dispositivo | Cifrado de IndexedDB + borrado al cerrar sesión |
| Robo de base de datos | Cifrado de campos sensibles en reposo |
| Token robado | Tokens hasheados, revocables, rate limiting |
| Cliente malicioso inyectando datos | Validación y autorización de todos los cambios en `sync/push` |
| Filtración por notificaciones | Payload de push sin datos sensibles |
| Abuso de endpoints | Rate limiting por IP y usuario |


---

## 16-bis. Datos semilla (catálogo inicial)

El catálogo de áreas de servicio y llamamientos se carga inicialmente en la base de datos mediante el script `seed-catalogo.sql`, generado a partir de las secciones 4 y 5. Contiene:

- **35 áreas de servicio** (17 de nivel Estaca, 9 de Barrio, 9 de Rama).
- **103 llamamientos** distribuidos en esas áreas.
- **116 reglas de solapamiento** (`calling_overlaps`): accesos adicionales que cada llamamiento otorga.
- **0 reglas de aprobación explícitas** (`calling_approvers`): todos los casos quedan cubiertos por `es_presidente` del área o por `es_aprobador_unidad`. La tabla existe para reglas futuras.

Notas sobre el seed:

- Los `id` de áreas y llamamientos son fijos (INT) para que las relaciones de solapamiento y aprobación sean estables.
- El campo `es_presidente` marca el rol con autoridad de aprobación dentro de cada área.
- El campo `es_aprobador_unidad` marca a quienes aprueban a los presidentes de otras áreas (Obispo, Presidente de Rama, Presidente de Estaca, sus consejeros y sus secretarios).
- Las áreas marcadas con `es_solo_solapamiento` (Consejo de Estaca, Consejo de Barrio, Consejo de Rama) no se solicitan directamente: se obtienen solo por solapamiento.
- Las estacas, unidades (barrios/ramas) y usuarios NO se incluyen en el seed: se crean dinámicamente cuando los usuarios se registran.

---

## 17. Mejoras futuras (fuera del MVP)

Ideas identificadas durante el diseño que se posponen para versiones posteriores, para no ampliar el alcance del MVP:

- **Asignaciones de ministración:** registrar a quiénes ministra cada pareja (familias o personas asignadas), más allá del nombre de la pareja (ver sección 9-bis.2).
- **Responsable por subtarea:** permitir que cada subtarea tenga su propio responsable asignable. En el MVP las subtareas no tienen responsable (ver CU-11).
- **Distrito:** soportar el nivel Distrito, equivalente a Estaca, hoy fuera de alcance (ver sección 3.3).
- **Fusión por campo en sincronización:** refinar la resolución de conflictos last-write-wins hacia una fusión por campo, para evitar que un cambio pise campos que el otro usuario no tocó (ver sección 11.3.4).
- **Auditoría de visualización:** registrar no solo acciones críticas sino también accesos de lectura a datos sensibles, si se requiere mayor trazabilidad (ver sección 16.8).

