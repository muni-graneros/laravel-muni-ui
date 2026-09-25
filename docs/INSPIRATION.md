# Inspiración de fuentes que NO se pueden copiar

Este documento recoge lo que se aprendió **mirando** productos comerciales cuyas licencias
prohíben redistribuir su código. `laravel-muni-ui` se entrega a otras municipalidades bajo licencia
privada, así que de estas fuentes no sale ni una línea.

**Qué se hizo:** abrir sus demos públicas en el navegador, capturar pantalla y anotar composición,
densidad e ideas de interacción.

**Qué no se hizo, y consta:** no se clonó, no se descargó, no se instaló nada, no se abrió el
código fuente para transcribirlo, y no hay markup, CSS ni JavaScript de estas fuentes en ningún
archivo del paquete. Las ideas de disposición y de composición no son objeto de protección; la
expresión sí, y esa se queda donde está (Ley 17.336).

Capturas en `refs/inspiration/`, fuera del repositorio.

## Qué se visitó

| Proveedor | Qué se abrió | Captura |
|---|---|---|
| TailAdmin | panel de comercio de la versión de pago | `tailadmin-dashboard.png` |
| Flux (Livewire) | portada con la pantalla de ajustes embebida | `fluxui-home.png` |
| Metronic (KeenThemes) | selector de demos y panel «Multipurpose» | `metronic-demo1.png` |
| Vuexy (PixInvent) | panel de analítica de la versión de pago | `vuexy-dashboard.png` |
| Sneat PRO (ThemeSelection) | panel de analítica de la versión de pago | `sneat-pro-dashboard.png` |
| Tailwind Plus | índice de bloques de Application UI | `tailwindplus-application-ui.png` |
| Flowbite Blocks | índice de bloques, libres y de pago | `flowbite-blocks.png` |
| Creative Tim | catálogo de paneles Tailwind | `creativetim-tailwind-dashboards.png` |
| AdminLTE | catálogo de plantillas de pago | `adminlte-premium.png` |

No se abrieron las demos de David UI PRO ni de Materio PRO: su contenido es el mismo lenguaje
visual de sus hermanos ya vistos y no aportaba una composición nueva.

## Las cinco ideas que vale la pena recrear

**1. El catálogo se ordena por papel en la página, no por nombre de widget.** Tailwind Plus
agrupa en «Application Shells», «Headings», «Table Headers», y cada grupo dice cuántas piezas
tiene. Flowbite hace lo mismo y etiqueta cada bloque como interfaz de aplicación o de marketing.
La consecuencia práctica es que «cabecera de tabla» existe como pieza propia, separada de la
tabla: el título, el buscador, los filtros y las acciones son un bloque que se reutiliza sobre
tablas distintas. Nuestro README lista componentes por nombre y no tiene esa capa.

**2. La franja de indicadores lleva la variación en texto, no solo en color.** En los cuatro
paneles vistos la cifra grande va acompañada de una píldora con la flecha **y** el porcentaje
escrito. Es exactamente la regla que nos exige WCAG y ellos la cumplen por estética, no por norma.
Nuestro `stat` ya lo hace; el minigráfico que lo acompaña, en cambio, no tiene alternativa
textual.

**3. La tarjeta de meta con anillo y pie de tres cifras.** TailAdmin remata su medidor circular con
una franja inferior partida en tres columnas (objetivo, ingresos, hoy), cada una con su flecha de
dirección. Vuexy hace lo mismo bajo su gráfico de barras, con icono, etiqueta, valor y una
minibarra de proporción. Es densidad bien resuelta: tres datos secundarios sin gastar una tarjeta
más. Sirve tal cual para «solicitudes del mes: meta, ingresadas, resueltas hoy».

**4. La pantalla de ajustes a dos columnas con la explicación al lado.** Flux, que es la
biblioteca oficial de Livewire y por tanto la más cercana a nuestro stack, coloca a la izquierda
el título de la sección con una frase que explica para qué sirve, y a la derecha los campos con su
ayuda propia. En un trámite municipal esa columna izquierda es donde va el fundamento legal del
dato que se pide, que hoy no tiene lugar en nuestros formularios.

**5. La barra lateral con recuento y con grupo plegable.** Flux pone el número de pendientes junto
al ítem («Inbox 12») y agrupa favoritos en una sección plegable. Vuexy pone el contador en el
grupo, no en el ítem. Para un menú municipal con cinco áreas y trámites pendientes por área, el
contador en el grupo es el que informa sin obligar a desplegar.

## Densidad: lo que no hay que imitar

Los cuatro paneles de pago son **más aireados** de lo que necesita un mesón municipal. TailAdmin
usa radios de 16 px, tarjetas separadas por 24 px y cifras enormes; en una pantalla de 1440 px
muestra cuatro bloques y obliga a desplazarse. Vuexy y Metronic son bastante más densos y se
acercan más a lo que sirve: Metronic mete seis indicadores con minigráfico en el alto que
TailAdmin gasta en dos.

Ninguno resuelve el caso real de un funcionario que revisa doscientas solicitudes: todos están
construidos para la captura de pantalla de la tienda, no para el uso diario. La conclusión es la
que ya está escrita en nuestras convenciones, ahora con evidencia: **priorizar densidad sobre
aire**, y usar estas referencias para composición, nunca para espaciado.

## Detalles de interacción anotados

- **Buscador con atajo visible.** TailAdmin y Vuexy dibujan la tecla del atajo dentro del campo.
  Nuestra paleta de comandos ya usa el atajo pero no lo muestra en ninguna parte.
- **Barra superior flotante.** Vuexy separa la barra superior del borde y la redondea. Es
  atractivo y cuesta altura útil: se descarta.
- **Indicador de conexión sobre el avatar.** Vuexy marca el estado con un punto verde sobre la
  foto. Nuestro `topbar` ya trae el estado del sistema como insignia con texto, que es mejor.
- **Insignia «NUEVO» en el menú.** TailAdmin marca módulos recientes. Para el ecosistema municipal
  sirve al desplegar un módulo nuevo, con fecha de caducidad para que no se quede pegada.
- **Selector de rango de fechas junto a las pestañas del gráfico.** TailAdmin combina pestañas de
  serie y rango temporal en la misma fila de la cabecera de la tarjeta. Es la disposición correcta
  para nuestros informes por período.

## Lo que ninguno resuelve y nos toca a nosotros

Ninguna de las demos vistas es navegable por teclado de punta a punta, ninguna marca el ítem
activo del menú con `aria-current`, y ninguna anuncia el cambio de tema. Es el mismo resultado que
dio la lectura de las nueve familias de código abierto. La accesibilidad no se hereda de estas
fuentes: se escribe acá.
