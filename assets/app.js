const app = document.getElementById('app');
let CONFIG = {}, SESION = { logged:false, rol:null }, CARRITO = [];
const EN_ADMIN = new URLSearchParams(location.search).get('action') === 'admin';
const MAX_CANTIDAD = 9999;

// El carrito vive en localStorage y lo comparten todas las pestañas. Se relee antes de cada cambio:
// si cada pestaña escribiera su propia copia, borraría lo que otra acaba de agregar.
const K_CARRITO = 'carrito', K_AVISO = 'tienda_aviso';
let almacenOk = true;
function recargarCarrito() {
  if (!almacenOk) return;
  try {
    const c = JSON.parse(localStorage.getItem(K_CARRITO) || '[]');
    CARRITO = Array.isArray(c) ? c.filter(i => i && i.key && i.cantidad > 0) : [];
  } catch (e) { CARRITO = []; }
}
function cambiarCarrito(fn) { recargarCarrito(); fn(); guardarCarrito(); }
recargarCarrito();

function avisarPestanas(tipo, dato = '') {
  try { localStorage.setItem(K_AVISO, JSON.stringify({ tipo, dato, t: Date.now() })); } catch (e) {}
}

const svg = p => `<svg class="i" viewBox="0 0 24 24">${p}</svg>`;
const IC = {
  box: svg('<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>'),
  plus: svg('<path d="M12 5v14M5 12h14"/>'), minus: svg('<path d="M5 12h14"/>'),
  search: svg('<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>'),
  home: svg('<path d="M3 11 12 3l9 8v10H3z"/>'), tag: svg('<path d="M3 12V3h9l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.5"/>'),
  folder: svg('<path d="M3 6h7l2 2h9v11H3z"/>'), users: svg('<circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6s7 2 7 6M17 4a4 4 0 0 1 0 8M22 21c0-3-2-5-4-5.5"/>'),
  log: svg('<path d="M6 3h9l4 4v14H6z"/><path d="M9 12h7M9 16h7"/>'), cog: svg('<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/>'),
  chart: svg('<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>'), shop: svg('<path d="M4 9h16l-1-5H5zM5 9v11h14V9"/>')
};

const fallo = r => !r || r.ok === false;
const miniatura = ruta => (ruta || '').replace(/\.webp$/, '_t.webp');
// Las imágenes antiguas no tienen miniatura: si falla, se usa la original (ver listener de 'error').
const imgT = (ruta, alt = '', extra = '') => {
  const t = miniatura(ruta);
  return `<img src="${esc(t)}" ${t !== ruta ? `data-full="${esc(ruta)}"` : ''} alt="${esc(alt)}" loading="lazy" decoding="async" ${extra}>`;
};
document.addEventListener('error', e => {
  const im = e.target;
  if (im.tagName === 'IMG' && im.dataset.full) { const f = im.dataset.full; delete im.dataset.full; im.src = f; }
}, true);

const esc = s => (s ?? '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const nl2br = s => esc(s).replace(/\n/g, '<br>');
const money = n => 'S/ ' + (parseFloat(n) || 0).toFixed(2);
const $ = id => document.getElementById(id);
const TIPOS = { unidad:'Unidad', docena:'Docena', caja:'Caja' };

function toast(msg, error=false) {
  const t = $('toast');
  t.textContent = msg; t.className = error ? 'error show' : 'show';
  clearTimeout(window.__tt);
  window.__tt = setTimeout(() => t.className = error ? 'error' : '', 2600);
}

// Cerrar ventanas al tocar el fondo o con Escape
document.addEventListener('click', e => {
  if (e.target.classList?.contains('overlay') && e.target.id !== 'lightbox') e.target.classList.remove('on');
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.overlay.on').forEach(o => o.classList.remove('on')); });

window.abrirLightbox = src => { if (!src) return; $('lightboxImg').src = src; $('lightbox').classList.add('on'); };
window.cerrarLightbox = () => { $('lightbox').classList.remove('on'); $('lightboxImg').src = ''; };

const CAMBIA_DATOS = /^api_(admin_(guardar|toggle|campania_default)|subir_imagen|eliminar_imagen)/;
let sesionVencida = false;

async function api(action, data=null) {
  const opt = { method: data ? 'POST' : 'GET' };
  if (data instanceof FormData) opt.body = data;
  else if (data) { opt.headers = {'Content-Type':'application/json'}; opt.body = JSON.stringify(data); }
  let r;
  try { r = await fetch('?action=' + action, opt); }
  catch (e) { toast('No hay conexión con el servidor. Revisa tu internet e inténtalo de nuevo.', true); return { ok:false, msg:'Sin conexión' }; }
  let j;
  try { j = await r.json(); }
  catch (e) { j = { ok:false, msg:'El servidor tuvo un problema. Inténtalo de nuevo.' }; }
  if (r.status === 403 && !sesionVencida) {
    sesionVencida = true;
    toast('Tu sesión terminó. Vuelve a ingresar.', true);
    avisarPestanas('sesion');
    setTimeout(() => { location.href = '?action=home'; }, 1500);
  }
  if (!fallo(j) && CAMBIA_DATOS.test(action)) avisarPestanas('datos', action);
  return j;
}

// Lo que pasa en una pestaña se refleja en las demás del mismo navegador.
let tRefresco;
function atenderAviso(a) {
  if (a.tipo === 'sesion') { if (EN_ADMIN) location.reload(); else cargarSesion(true); return; }
  if (a.tipo !== 'datos') return;
  if (a.dato === 'api_admin_guardar_ajustes') cargarConfig(true);
  clearTimeout(tRefresco);
  tRefresco = setTimeout(() => {
    const editando = $('modalFormDinamico')?.classList.contains('on');
    if (EN_ADMIN && SECCION && SECCION !== 'ajustes' && !editando) seccionAdmin(SECCION, true);
  }, 300);
}
window.addEventListener('storage', e => {
  if (e.key === K_CARRITO || e.key === null) {
    recargarCarrito(); pintarBadge();
    if ($('modalCarrito').classList.contains('on')) renderCarrito();
  } else if (e.key === K_AVISO && e.newValue) {
    try { atenderAviso(JSON.parse(e.newValue)); } catch (err) {}
  }
});
// Al volver con el botón Atrás, la página restaurada pudo perderse los avisos.
window.addEventListener('pageshow', e => { if (e.persisted) { recargarCarrito(); pintarBadge(); cargarSesion(true); } });

async function cargarConfig(fresh = false) {
  const c = (!fresh && window.__BOOT) ? window.__BOOT.config : await api('api_config');
  if (fallo(c)) return;
  CONFIG = c;
  $('nombrePagina').textContent = CONFIG.nombre_pagina || 'Mi Tienda';
  document.title = CONFIG.nombre_pagina || 'Mi Tienda';
  const lb = $('logoBox');
  if (CONFIG.logo && lb) {
    if (lb.tagName === 'IMG') lb.src = CONFIG.logo;
    else lb.outerHTML = `<img src="${esc(CONFIG.logo)}" alt="Logo" id="logoBox">`;
  }
  $('favicon').href = CONFIG.logo || 'data:,';
}

async function cargarSesion(fresh = false) {
  const s = (!fresh && window.__BOOT) ? window.__BOOT.session : await api('api_sesion');
  if (fallo(s)) return;
  SESION = s;
  $('btnLogin').style.display = SESION.logged ? 'none' : '';
  $('btnLogout').style.display = SESION.logged ? '' : 'none';
  $('btnAdmin').style.display = (SESION.logged && SESION.rol === 'admin') ? '' : 'none';
}

// ============ TIENDA ============
async function vistaHome() {
  const { default: def, campanias } = window.__BOOT; // ya vienen incrustados en la página: 0 peticiones
  let page = 1, cargando = false, fin = false, buscar = '', camp = parseInt(def.id) || 0, total = 0, gen = 0;
  window.__pc = {};

  app.innerHTML = `
    <div class="wrap">
      <section class="hero">
        <h1>${esc(CONFIG.nombre_pagina || 'Mi Tienda')}</h1>
        <p>Elige tus productos, arma tu carrito y envía el pedido por WhatsApp.</p>
        <div class="buscador">${IC.search}<input id="fBuscar" type="search" placeholder="Busca por nombre o código" aria-label="Buscar productos"></div>
      </section>
      <nav class="chips" id="chips" aria-label="Campañas"></nav>
      <p class="resumen" id="resumen"></p>
      <div class="grid" id="grid"></div>
      <div class="vacio" id="cargandoMas" style="display:none">Cargando más productos…</div>
    </div>`;

  const pintarChips = () => {
    $('chips').innerHTML = [{id:0,nombre:'Todas'}, ...campanias].map(c =>
      `<button class="chip ${c.id == camp ? 'on' : ''}" data-c="${c.id}">${esc(c.nombre)}</button>`).join('');
    $('chips').querySelectorAll('.chip').forEach(b => b.addEventListener('click', () => { camp = parseInt(b.dataset.c); pintarChips(); reiniciar(); }));
  };
  pintarChips();
  $('grid').addEventListener('click', e => { const c = e.target.closest('.card'); if (c) verProducto(window.__pc[c.dataset.id]); });
  $('grid').addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.classList.contains('card')) verProducto(window.__pc[e.target.dataset.id]); });

  let deb;
  $('fBuscar').addEventListener('input', e => { clearTimeout(deb); deb = setTimeout(() => { buscar = e.target.value.trim(); reiniciar(); }, 350); });

  function reiniciar() { gen++; cargando = false; page = 1; fin = false; $('cargandoMas').style.display = 'none'; $('grid').innerHTML = ''; cargar(); }

  function precioPrincipal(p) {
    for (const t of ['unidad','docena','caja']) if (p['precio_' + t] > 0) return t;
    return null;
  }

  function tarjeta(p, i) {
    const img = p.imagenes.length ? p.imagenes[0].ruta : '';
    const main = precioPrincipal(p);
    const otros = ['unidad','docena','caja'].filter(t => t !== main && p['precio_' + t] > 0);
    const el = document.createElement('article');
    el.className = 'card';
    el.tabIndex = 0;
    el.innerHTML = `
      <div class="ph">${img ? imgT(img, p.nombre, 'width="300" height="300"') : IC.box}</div>
      <div class="cb">
        <h3>${esc(p.nombre)}</h3>
        <div class="d">${esc(p.descripcion || '')}</div>
        ${main ? `<div class="pr">${money(p['precio_' + main])}<small>por ${main}</small></div>
          <div class="tags">${otros.map(t => `<span class="tag">${TIPOS[t]} ${money(p['precio_' + t])}</span>`).join('')}</div>`
          : '<div class="consultar">Consulta el precio</div>'}
      </div>
      ${main ? `<button class="add" aria-label="Ver ${esc(p.nombre)}">${IC.plus}</button>` : ''}`;
    el.dataset.id = p.id;
    return el;
  }

  async function cargar() {
    if (cargando || fin) return;
    cargando = true;
    const g = gen;
    if (page === 1) $('grid').innerHTML = Array(8).fill('<div class="sk"></div>').join('');
    else $('cargandoMas').style.display = 'block';
    const r = await api('api_productos', { page, buscar, campania: camp });
    if (g !== gen) return; // llegó una respuesta de una búsqueda anterior
    $('cargandoMas').style.display = 'none';
    if (fallo(r)) {
      cargando = false;
      if (page === 1) {
        $('grid').innerHTML = '<div class="vacio" style="grid-column:1/-1"><b>No pudimos cargar los productos</b><br><button class="btn btn-soft" id="btnReintentar">Reintentar</button></div>';
        $('btnReintentar').addEventListener('click', reiniciar);
      }
      return;
    }
    if (page === 1) $('grid').innerHTML = '';
    const lista = r.productos || [];
    if (page === 1) {
      total = parseInt(r.total) || 0;
      $('resumen').textContent = total ? `${total} producto${total === 1 ? '' : 's'}` : '';
    }
    if (!lista.length) {
      fin = true;
      if (page === 1) $('grid').innerHTML = '<div class="vacio" style="grid-column:1/-1"><b>No encontramos productos</b>Prueba con otra palabra o elige otra campaña.</div>';
    } else {
      const fr = document.createDocumentFragment();
      lista.forEach(p => { window.__pc[p.id] = p; fr.appendChild(tarjeta(p)); });
      $('grid').appendChild(fr);
      page++;
      if (!r.more) fin = true;
    }
    cargando = false;
    if (!fin) requestAnimationFrame(() => { if (cen.getBoundingClientRect().top < innerHeight + 600) cargar(); });
  }

  // Carga infinita con IntersectionObserver (sin escuchar cada scroll)
  const cen = document.createElement('div');
  cen.style.height = '1px';
  $('cargandoMas').before(cen);
  new IntersectionObserver(es => { if (es[0].isIntersecting) cargar(); }, { rootMargin: '600px' }).observe(cen);
  cargar();
}

window.verProducto = function(p) {
  if (!p) return;
  const imgs = p.imagenes.length ? p.imagenes : [];
  const tipos = ['unidad','docena','caja'].filter(t => p['precio_' + t] > 0);
  let tipo = tipos[0] || null, cant = 1;

  $('modalProductoCont').innerHTML = `
    <button class="x" onclick="cerrarModalProducto()" aria-label="Cerrar">✕</button>
    <div class="det">
      <div class="gal">
        <div class="main" id="gMain">${imgs[0] ? `<img src="${esc(imgs[0].ruta)}" alt="${esc(p.nombre)}">` : IC.box}</div>
        ${imgs.length > 1 ? `<div class="th">${imgs.map((im, i) => imgT(im.ruta, '', `class="${i === 0 ? 'on' : ''}" data-g="${esc(im.ruta)}"`)).join('')}</div>` : ''}
      </div>
      <div class="info">
        <h2>${esc(p.nombre)}</h2>
        <div class="meta">Código ${esc(p.codigo)} · ${esc(p.categoria || 'Sin categoría')}</div>
        <div class="txt">${nl2br(p.descripcion_detallada || p.descripcion || '')}</div>
        ${tipo ? `
          <div class="opts" id="opts">${tipos.map(t => `<button class="opt ${t === tipo ? 'on' : ''}" data-t="${t}"><span>${TIPOS[t]}</span><b>${money(p['precio_' + t])}</b></button>`).join('')}</div>
          <div class="qty-row"><span style="font-weight:600">Cantidad</span>
            <div class="step"><button id="qm" aria-label="Menos">${IC.minus}</button><input type="number" id="qv" value="1" min="1" max="${MAX_CANTIDAD}" aria-label="Cantidad"><button id="qp" aria-label="Más">${IC.plus}</button></div></div>
          <button class="btn btn-pri btn-block" id="btnAdd"></button>`
        : '<p class="txt">Este producto aún no tiene precio. Escríbenos por WhatsApp y te ayudamos.</p>'}
      </div>
    </div>`;
  $('modalProducto').classList.add('on');

  $('gMain').addEventListener('click', () => abrirLightbox($('gMain').querySelector('img')?.src));
  document.querySelectorAll('#modalProductoCont .th img').forEach(im => im.addEventListener('click', () => {
    $('gMain').innerHTML = `<img src="${esc(im.dataset.g)}" alt="">`;
    document.querySelectorAll('#modalProductoCont .th img').forEach(x => x.classList.toggle('on', x === im));
  }));
  if (!tipo) return;

  const refrescar = () => {
    $('qv').value = cant;
    $('btnAdd').textContent = `Agregar al carrito · ${money(p['precio_' + tipo] * cant)}`;
  };
  document.querySelectorAll('#opts .opt').forEach(b => b.addEventListener('click', () => {
    tipo = b.dataset.t;
    document.querySelectorAll('#opts .opt').forEach(x => x.classList.toggle('on', x === b));
    refrescar();
  }));
  $('qm').addEventListener('click', () => { cant = Math.max(1, cant - 1); refrescar(); });
  $('qp').addEventListener('click', () => { cant = Math.min(MAX_CANTIDAD, cant + 1); refrescar(); });
  $('qv').addEventListener('input', e => { cant = Math.min(MAX_CANTIDAD, Math.max(1, parseInt(e.target.value) || 1)); $('btnAdd').textContent = `Agregar al carrito · ${money(p['precio_' + tipo] * cant)}`; });
  $('qv').addEventListener('change', refrescar);
  $('btnAdd').addEventListener('click', () => agregarAlCarrito(p, tipo, cant));
  refrescar();
};
window.cerrarModalProducto = () => $('modalProducto').classList.remove('on');

function agregarAlCarrito(p, tipo, cantidad) {
  const key = p.id + '_' + tipo;
  cambiarCarrito(() => {
    const ex = CARRITO.find(i => i.key === key);
    if (ex) ex.cantidad = Math.min(MAX_CANTIDAD, ex.cantidad + cantidad);
    else CARRITO.push({ key, id:p.id, codigo:p.codigo, nombre:p.nombre, descripcion:p.descripcion,
      imagen:(p.imagenes[0] && p.imagenes[0].ruta) || '', tipo, precio:parseFloat(p['precio_' + tipo]), cantidad });
  });
  cerrarModalProducto(); toast('Agregado al carrito');
}

function guardarCarrito() {
  try { localStorage.setItem(K_CARRITO, JSON.stringify(CARRITO)); almacenOk = true; }
  catch (e) { almacenOk = false; } // sin almacenamiento: el carrito sigue en la memoria de esta pestaña
  pintarBadge();
}
function pintarBadge() {
  const n = CARRITO.reduce((a, i) => a + i.cantidad, 0);
  $('badgeCarrito').textContent = n || '';
}

window.abrirCarrito = () => { recargarCarrito(); pintarBadge(); renderCarrito(); $('modalCarrito').classList.add('on'); };
window.cerrarCarrito = () => $('modalCarrito').classList.remove('on');

function renderCarrito() {
  const cont = $('carritoCont');
  if (!CARRITO.length) {
    cont.innerHTML = `<div class="vacio" style="margin:auto"><b>Tu carrito está vacío</b>Agrega productos del catálogo para armar tu pedido.<br><br><button class="btn btn-soft" onclick="cerrarCarrito()">Ver productos</button></div>`;
    return;
  }
  let total = 0;
  const items = CARRITO.map(i => {
    const sub = i.precio * i.cantidad; total += sub;
    return `<div class="ci">
      <div class="im">${i.imagen ? imgT(i.imagen) : IC.box}</div>
      <div>
        <div class="nm">${esc(i.nombre)}</div>
        <div class="sb">${esc(TIPOS[i.tipo] || i.tipo)} · ${money(i.precio)} c/u</div>
        <div class="step"><button data-k="${esc(i.key)}" data-d="-1" aria-label="Menos">${IC.minus}</button><span>${i.cantidad}</span><button data-k="${esc(i.key)}" data-d="1" aria-label="Más">${IC.plus}</button></div>
      </div>
      <div><div class="tot">${money(sub)}</div><button class="del" data-x="${esc(i.key)}">Quitar</button></div>
    </div>`;
  }).join('');
  cont.innerHTML = `<div class="lista">${items}</div>
    <footer>
      <div class="total-row"><span>Total</span><span>${money(total)}</span></div>
      <div class="acts">
        <button class="btn btn-wa btn-block" id="btnWa">Pedir por WhatsApp</button>
        <button class="btn btn-line btn-block" id="btnPrint">Imprimir boleta</button>
      </div>
    </footer>`;
  // Se busca por clave y no por posición: otra pestaña pudo cambiar la lista.
  cont.querySelectorAll('[data-d]').forEach(b => b.addEventListener('click', () => {
    cambiarCarrito(() => {
      const it = CARRITO.find(i => i.key === b.dataset.k);
      if (it) it.cantidad = Math.min(MAX_CANTIDAD, Math.max(1, it.cantidad + parseInt(b.dataset.d)));
    });
    renderCarrito();
  }));
  cont.querySelectorAll('[data-x]').forEach(b => b.addEventListener('click', () => {
    cambiarCarrito(() => { CARRITO = CARRITO.filter(i => i.key !== b.dataset.x); });
    renderCarrito();
  }));
  $('btnWa').addEventListener('click', enviarWhatsApp);
  $('btnPrint').addEventListener('click', imprimirBoleta);
}

async function enviarWhatsApp() {
  recargarCarrito();
  if (!CARRITO.length) { renderCarrito(); pintarBadge(); return toast('El carrito está vacío', true); }
  let total = 0, msg = '*NUEVO PEDIDO*\n\n';
  CARRITO.forEach(i => {
    const sub = i.precio * i.cantidad; total += sub;
    msg += `▪️ ${i.nombre}${i.codigo ? ' (Cod: ' + i.codigo + ')' : ''}\n   Tipo: ${i.tipo} | Cant: ${i.cantidad}\n   Subtotal: ${money(sub)}\n\n`;
  });
  msg += `*TOTAL: ${money(total)}*`;
  const tel = (CONFIG.whatsapp || '').replace(/\D/g, '');
  // Debe abrirse antes de cualquier await: después, el navegador lo trata como ventana emergente y la bloquea.
  const ventana = window.open(`https://wa.me/${tel}?text=${encodeURIComponent(msg)}`, '_blank');
  if (!ventana) return toast('Tu navegador bloqueó WhatsApp. Permite las ventanas emergentes e inténtalo de nuevo.', true);
  const items = CARRITO.map(i => ({ id:i.id, cantidad:i.cantidad, tipo:i.tipo }));
  // Se vacía antes de registrar: así un doble clic o la otra pestaña no duplican la venta.
  cambiarCarrito(() => { CARRITO = []; });
  renderCarrito(); toast('Pedido enviado');
  await api('api_registrar_venta', { items });
}

function imprimirBoleta() {
  recargarCarrito();
  if (!CARRITO.length) { renderCarrito(); pintarBadge(); return toast('El carrito está vacío', true); }
  let total = 0;
  const filas = CARRITO.map(i => {
    const sub = i.precio * i.cantidad; total += sub;
    return `<tr><td>${esc(i.nombre)}${i.codigo ? `<br><small>Cod: ${esc(i.codigo)}</small>` : ''}<br><small>${esc(i.tipo)}</small></td><td style="text-align:center">${i.cantidad}</td><td style="text-align:right">${money(i.precio)}</td><td style="text-align:right">${money(sub)}</td></tr>`;
  }).join('');
  const header = (CONFIG.boleta_header || '').split('\n').map(l => `<div>${esc(l)}</div>`).join('');
  const pa = $('printArea');
  pa.innerHTML = `<div style="font-family:monospace;max-width:380px;margin:0 auto">
    <div style="text-align:center;margin-bottom:14px">${header}</div>
    <div style="text-align:center;border-bottom:1px dashed #000;padding-bottom:8px;margin-bottom:8px">BOLETA DE VENTA</div>
    <div>Fecha: ${new Date().toLocaleString()}</div>
    <table style="width:100%;border-collapse:collapse;margin-top:10px;font-size:12px;min-width:0"><thead><tr style="border-bottom:1px solid #000"><th align="left">Producto</th><th>Cant</th><th>P.U.</th><th align="right">Total</th></tr></thead><tbody>${filas}</tbody></table>
    <div style="text-align:right;margin-top:12px;font-weight:700;font-size:16px;border-top:1px dashed #000;padding-top:8px">TOTAL: ${money(total)}</div>
    <div style="text-align:center;margin-top:14px;font-size:11px">¡Gracias por su compra!</div></div>`;
  pa.style.display = 'block';
  window.addEventListener('afterprint', () => { pa.style.display = 'none'; }, { once:true });
  window.print();
}

// ============ INGRESO ============
window.abrirLogin = () => { $('modalLogin').classList.add('on'); setTimeout(() => $('loginUsuario').focus(), 50); };
window.cerrarLogin = () => $('modalLogin').classList.remove('on');
window.hacerLogin = async function() {
  const u = $('loginUsuario').value, p = $('loginPassword').value;
  if (!u || !p) return toast('Escribe tu usuario y contraseña', true);
  const r = await api('api_login', { usuario:u, password:p });
  if (!r.ok) return toast(r.msg, true);
  $('loginPassword').value = '';
  toast('Hola, ' + r.nombre);
  avisarPestanas('sesion');
  cerrarLogin(); await cargarSesion(true);
  if (r.rol === 'admin') location.href = '?action=admin';
};
$('loginPassword').addEventListener('keydown', e => { if (e.key === 'Enter') hacerLogin(); });
window.logout = async () => {
  const r = await api('api_logout', {});
  if (fallo(r)) return;
  avisarPestanas('sesion');
  location.href = '?action=home';
};

// ============ PANEL ADMIN ============
async function vistaAdmin() {
  if (!SESION.logged || SESION.rol !== 'admin') {
    app.innerHTML = '<div class="wrap"><div class="vacio"><b>Acceso restringido</b>Ingresa con una cuenta de administrador.<br><br><a class="btn btn-pri" href="?action=home" style="text-decoration:none">Volver a la tienda</a></div></div>';
    return;
  }
  const menu = [['inicio','chart','Resumen'],['productos','box','Productos'],['campanias','tag','Campañas'],['categorias','folder','Categorías'],['usuarios','users','Usuarios'],['auditoria','log','Historial'],['ajustes','cog','Ajustes']];
  app.innerHTML = `<div class="adm">
    <aside class="side"><h3>${esc(CONFIG.nombre_pagina || 'Mi Tienda')}</h3>
      ${menu.map(m => `<a href="?action=admin#${m[0]}" data-sec="${m[0]}">${IC[m[1]]}${m[2]}</a>`).join('')}
      <a href="?action=home">${IC.shop}Ver tienda</a>
    </aside><section class="adm-main" id="adminCont"></section></div>`;
  // Cada sección tiene su propia dirección: se puede abrir una distinta en cada pestaña.
  const irASeccion = () => {
    const sec = location.hash.slice(1);
    seccionAdmin(menu.some(m => m[0] === sec) ? sec : 'inicio');
  };
  window.addEventListener('hashchange', irASeccion);
  irASeccion();
}

const estado = (v, a='Activo', b='Inactivo') => `<span class="pill ${v == 1 ? 'ok' : 'off'}">${v == 1 ? a : b}</span>`;
const tabla = (heads, rows, vacio) => `<div class="tw"><table><thead><tr>${heads.map(h => `<th>${h}</th>`).join('')}</tr></thead><tbody>${rows || `<tr><td colspan="${heads.length}" class="vacio">${vacio}</td></tr>`}</tbody></table></div>`;
const cache = (arr) => { const m = {}; arr.forEach(x => m[x.id] = x); return m; };
const btnEd = id => `<button class="btn btn-soft btn-sm" data-editar="${id}">Editar</button>`;
const btnTg = (id, v, on='Desactivar', off='Activar') => `<button class="btn btn-bad btn-sm" data-toggle="${id}">${v == 1 ? on : off}</button>`;

let SECCION = null, genSeccion = 0;

// Con silencioso se actualiza sin mostrar "Cargando…" (cambios hechos desde otra pestaña).
window.seccionAdmin = async function(sec, silencioso = false) {
  SECCION = sec;
  const g = ++genSeccion;
  document.querySelectorAll('.side a[data-sec]').forEach(a => a.classList.toggle('on', a.dataset.sec === sec));
  const c = $('adminCont');
  if (!silencioso) c.innerHTML = '<p class="vacio">Cargando…</p>';
  // Descarta respuestas de una sección que ya se dejó y muestra el error si alguna petición falló.
  const listo = (...rs) => {
    if (g !== genSeccion) return false;
    const mal = rs.find(fallo);
    if (rs.some(fallo)) {
      c.innerHTML = `<div class="vacio"><b>No se pudo cargar esta sección</b>${esc(mal?.msg || '')}<br><br><button class="btn btn-soft" id="btnReintentar">Reintentar</button></div>`;
      $('btnReintentar').addEventListener('click', () => seccionAdmin(sec));
      return false;
    }
    return true;
  };
  const bind = (fEd, fTg, store) => {
    c.querySelectorAll('[data-editar]').forEach(b => b.addEventListener('click', () => fEd(store[b.dataset.editar])));
    c.querySelectorAll('[data-toggle]').forEach(b => b.addEventListener('click', () => fTg(b.dataset.toggle)));
  };

  if (sec === 'inicio') {
    const d = await api('api_admin_dashboard');
    if (!listo(d)) return;
    c.innerHTML = `<h1>Resumen</h1>
      <div class="stats">
        <div class="stat"><h4>Productos</h4><div class="n">${d.tp}</div></div>
        <div class="stat"><h4>Categorías</h4><div class="n">${d.tc}</div></div>
        <div class="stat"><h4>Campañas</h4><div class="n">${d.tca}</div></div>
        <div class="stat"><h4>Usuarios</h4><div class="n">${d.tu}</div></div>
      </div>
      <div class="tw"><h3>Productos más vendidos</h3>
        <table><thead><tr><th>Código</th><th>Producto</th><th>Vendidos</th><th>Ingresos</th></tr></thead>
        <tbody>${d.mv.map(p => `<tr><td>${esc(p.codigo)}</td><td>${esc(p.nombre)}</td><td>${parseFloat(p.vendidos)}</td><td>${money(p.ingresos)}</td></tr>`).join('') || '<tr><td colspan="4" class="vacio">Todavía no hay ventas registradas.</td></tr>'}</tbody></table></div>`;
  }

  else if (sec === 'productos') {
    const [prods, cats, camps] = await Promise.all([api('api_admin_productos'), api('api_admin_categorias'), api('api_admin_campanias')]);
    if (!listo(prods, cats, camps)) return;
    window.__cats = cats; window.__camps = camps;
    const rows = prods.map(p => `<tr>
      <td>${esc(p.codigo)}</td><td>${p.imagenes[0] ? `${imgT(p.imagenes[0].ruta, '', 'class="mini"')}` : '—'}</td>
      <td><b>${esc(p.nombre)}</b></td><td>${money(p.precio_unidad)}</td><td>${money(p.precio_docena)}</td><td>${money(p.precio_caja)}</td>
      <td>${esc(p.categoria || '—')}</td><td>${esc(p.campania || '—')}</td><td>${estado(p.estado)}</td>
      <td><div class="rowact">${btnEd(p.id)}${btnTg(p.id, p.estado)}</div></td></tr>`).join('');
    c.innerHTML = `<div class="bar"><h1>Productos</h1><button class="btn btn-pri" id="btnNuevo">${IC.plus}Agregar producto</button></div>` +
      tabla(['Código','Foto','Nombre','Unidad','Docena','Caja','Categoría','Campaña','Estado',''], rows, 'Aún no hay productos. Agrega el primero.');
    $('btnNuevo').addEventListener('click', () => formProducto(null));
    bind(formProducto, toggleProd, cache(prods));
  }

  else if (sec === 'campanias') {
    const camps = await api('api_admin_campanias');
    if (!listo(camps)) return;
    const rows = camps.map(x => `<tr><td>${x.id}</td><td><b>${esc(x.nombre)}</b></td><td>${esc(x.descripcion || '')}</td>
      <td>${x.activa_default == 1 ? '<span class="pill ok">Principal</span>' : '—'}</td><td>${estado(x.estado, 'Activa', 'Inactiva')}</td>
      <td><div class="rowact">${btnEd(x.id)}${btnTg(x.id, x.estado)}<button class="btn btn-line btn-sm" data-default="${x.id}">Hacer principal</button></div></td></tr>`).join('');
    c.innerHTML = `<div class="bar"><h1>Campañas</h1><button class="btn btn-pri" id="btnNuevo">${IC.plus}Nueva campaña</button></div>` +
      tabla(['ID','Nombre','Descripción','Principal','Estado',''], rows, 'Aún no hay campañas.');
    $('btnNuevo').addEventListener('click', () => formCampania(null));
    bind(formCampania, toggleCamp, cache(camps));
    c.querySelectorAll('[data-default]').forEach(b => b.addEventListener('click', () => setDefaultCamp(b.dataset.default)));
  }

  else if (sec === 'categorias') {
    const cats = await api('api_admin_categorias');
    if (!listo(cats)) return;
    const rows = cats.map(x => `<tr><td>${x.id}</td><td><b>${esc(x.nombre)}</b></td><td>${x.total}</td><td>${estado(x.estado, 'Activa', 'Inactiva')}</td>
      <td><div class="rowact">${btnEd(x.id)}${btnTg(x.id, x.estado)}</div></td></tr>`).join('');
    c.innerHTML = `<div class="bar"><h1>Categorías</h1><button class="btn btn-pri" id="btnNuevo">${IC.plus}Nueva categoría</button></div>` +
      tabla(['ID','Nombre','Productos','Estado',''], rows, 'Aún no hay categorías.');
    $('btnNuevo').addEventListener('click', () => formCategoria(null));
    bind(formCategoria, toggleCat, cache(cats));
  }

  else if (sec === 'usuarios') {
    const us = await api('api_admin_usuarios');
    if (!listo(us)) return;
    const rows = us.map(u => `<tr><td>${u.id}</td><td><b>${esc(u.nombre)}</b></td><td>${esc(u.usuario)}</td><td>${esc(u.rol)}</td><td>${estado(u.estado)}</td>
      <td><div class="rowact">${btnEd(u.id)}${btnTg(u.id, u.estado)}</div></td></tr>`).join('');
    c.innerHTML = `<div class="bar"><h1>Usuarios</h1><button class="btn btn-pri" id="btnNuevo">${IC.plus}Nuevo usuario</button></div>` +
      tabla(['ID','Nombre','Usuario','Rol','Estado',''], rows, 'Aún no hay usuarios.');
    $('btnNuevo').addEventListener('click', () => formUsuario(null));
    bind(formUsuario, toggleUser, cache(us));
  }

  else if (sec === 'auditoria') {
    const aud = await api('api_admin_auditoria');
    if (!listo(aud)) return;
    const rows = aud.map(a => `<tr><td>${esc(a.fecha)}</td><td>${esc(a.usuario_nombre)}</td><td>${esc(a.accion)}</td><td>${esc(a.tabla)}</td><td>${esc(a.detalles)}</td></tr>`).join('');
    c.innerHTML = '<h1>Historial de acciones</h1>' + tabla(['Fecha','Usuario','Acción','Sección','Detalle'], rows, 'Aún no hay registros.');
  }

  else if (sec === 'ajustes') {
    const aj = await api('api_admin_ajustes');
    if (!listo(aj)) return;
    const m = {}; aj.forEach(a => m[a.clave] = a.valor);
    c.innerHTML = `<h1>Ajustes</h1><div class="tw" style="padding:22px"><div class="fgrid fld">
      <div><label>Nombre de la tienda</label><input id="aj_nombre" value="${esc(m.nombre_pagina || '')}"></div>
      <div><label>WhatsApp (con código de país, ej. 51987654321)</label><input id="aj_wa" value="${esc(m.whatsapp || '')}"></div>
      <div class="full"><label>Encabezado de la boleta (una línea por renglón)</label><textarea id="aj_boleta">${esc(m.boleta_header || '')}</textarea></div>
      <div class="full"><label>Logo</label>${m.logo ? `<img src="${esc(m.logo)}" alt="Logo actual" style="max-height:80px;margin-bottom:8px;border-radius:10px">` : ''}
        <input type="file" id="aj_logo" accept="image/png,image/jpeg,image/gif,image/webp"></div>
      <div class="full"><button class="btn btn-pri" id="btnGuardarAjustes">Guardar ajustes</button></div></div></div>`;
    $('btnGuardarAjustes').addEventListener('click', guardarAjustes);
  }
};

window.guardarAjustes = async function() {
  const fd = new FormData();
  fd.append('nombre_pagina', $('aj_nombre').value);
  fd.append('whatsapp', $('aj_wa').value);
  fd.append('boleta_header', $('aj_boleta').value);
  const lg = $('aj_logo').files[0];
  if (lg) fd.append('logo', lg);
  const j = await api('api_admin_guardar_ajustes', fd);
  if (j.ok) { toast('Ajustes guardados'); await cargarConfig(true); seccionAdmin('ajustes'); }
  else toast(j.msg || 'No se pudieron guardar los ajustes', true);
};

// ---- Formularios (hoja modal) ----
function modalForm(titulo, html, onSave) {
  let m = $('modalFormDinamico');
  if (!m) {
    m = document.createElement('div');
    m.id = 'modalFormDinamico'; m.className = 'overlay';
    m.innerHTML = '<div class="sheet" style="max-width:720px"><button class="x" id="cerrarForm" aria-label="Cerrar">✕</button><div id="modalFormBody"></div></div>';
    document.body.appendChild(m);
    $('cerrarForm').addEventListener('click', () => m.classList.remove('on'));
  }
  $('modalFormBody').innerHTML = `<h2>${esc(titulo)}</h2>${html}<div style="margin-top:18px;display:flex;justify-content:flex-end;gap:8px"><button class="btn btn-line" id="btnCancelForm">Cancelar</button><button class="btn btn-pri" id="btnSaveForm">Guardar</button></div>`;
  m.classList.add('on');
  $('btnCancelForm').onclick = () => m.classList.remove('on');
  // Bloqueado mientras guarda: un doble clic crearía dos registros.
  $('btnSaveForm').onclick = async e => {
    const b = e.currentTarget;
    b.disabled = true;
    try { await onSave(); } finally { b.disabled = false; }
  };
}
const cerrarForm = () => $('modalFormDinamico').classList.remove('on');
const optsSel = (arr, sel, vacio) => `<option value="">${vacio}</option>` + arr.map(c => `<option value="${c.id}" ${sel == c.id ? 'selected' : ''}>${esc(c.nombre)}</option>`).join('');
const selEstado = (v, a, b) => `<select id="f_est"><option value="1" ${v != 0 ? 'selected' : ''}>${a}</option><option value="0" ${v == 0 ? 'selected' : ''}>${b}</option></select>`;

window.formProducto = async function(p) {
  let codigoAuto = '';
  if (!p) {
    const r = await api('api_admin_siguiente_codigo');
    if (fallo(r) || !r.codigo) return toast(r?.msg || 'No se pudo preparar el producto', true);
    codigoAuto = r.codigo;
  }
  const html = `<div class="fgrid fld">
    <div><label>Código${!p ? ' (automático)' : ''}</label><input id="f_codigo" maxlength="50" value="${esc(p?.codigo || codigoAuto)}" ${!p ? 'readonly' : ''}></div>
    <div><label>Nombre</label><input id="f_nombre" maxlength="150" value="${esc(p?.nombre || '')}"></div>
    <div class="full"><label>Descripción corta</label><input id="f_desc" value="${esc(p?.descripcion || '')}"></div>
    <div class="full"><label>Descripción detallada</label><textarea id="f_descd">${esc(p?.descripcion_detallada || '')}</textarea></div>
    <div><label>Precio por unidad</label><input type="number" step="0.01" min="0" max="99999999.99" id="f_pu" value="${p?.precio_unidad || 0}"></div>
    <div><label>Precio por docena</label><input type="number" step="0.01" min="0" max="99999999.99" id="f_pd" value="${p?.precio_docena || 0}"></div>
    <div><label>Precio por caja</label><input type="number" step="0.01" min="0" max="99999999.99" id="f_pc" value="${p?.precio_caja || 0}"></div>
    <div><label>Categoría</label><select id="f_cat">${optsSel(window.__cats || [], p?.categoria_id, 'Sin categoría')}</select></div>
    <div><label>Campaña</label><select id="f_camp">${optsSel(window.__camps || [], p?.campania_id, 'Sin campaña')}</select></div>
    <div><label>Estado</label>${selEstado(p ? p.estado : 1, 'Activo', 'Inactivo')}</div>
    <div class="full"><label>Imágenes</label>
      <div class="thumbs-adm">${(p?.imagenes || []).map(im => `<div><img src="${esc(im.ruta)}" alt=""><button data-elim-img="${im.id}" aria-label="Eliminar imagen">×</button></div>`).join('') || '<span style="font-size:13px;color:var(--mute)">Sin imágenes</span>'}</div>
      <input type="file" id="f_img" accept="image/png,image/jpeg,image/gif,image/webp" multiple>
      <p style="font-size:12px;color:var(--mute);margin-top:4px">${p ? 'Sube más imágenes o quita las actuales con la ×.' : 'Puedes elegir varias imágenes: se suben al guardar.'}</p></div>
  </div>`;
  modalForm(p ? 'Editar producto' : 'Nuevo producto', html, async () => {
    const data = { id:p?.id || 0, codigo:$('f_codigo').value.trim(), nombre:$('f_nombre').value.trim(), descripcion:$('f_desc').value,
      descripcion_detallada:$('f_descd').value, precio_unidad:$('f_pu').value, precio_docena:$('f_pd').value, precio_caja:$('f_pc').value,
      categoria_id:$('f_cat').value, campania_id:$('f_camp').value, estado:$('f_est').value };
    if (!data.codigo || !data.nombre) return toast('Completa el código y el nombre', true);
    const r = await api('api_admin_guardar_producto', data);
    if (!r.ok) return toast(r.msg || 'No se pudo guardar', true);
    const fallidas = [];
    for (const f of $('f_img').files) {
      const fd = new FormData(); fd.append('producto_id', r.id); fd.append('imagen', f);
      const j = await api('api_subir_imagen', fd);
      if (!j.ok) fallidas.push(`${f.name}: ${j.msg || 'error'}`);
    }
    toast(fallidas.length ? `Producto guardado, pero no se subió: ${fallidas.join('; ')}` : 'Producto guardado', fallidas.length > 0);
    cerrarForm(); seccionAdmin('productos');
  });
  document.querySelectorAll('[data-elim-img]').forEach(b => b.addEventListener('click', async () => {
    if (!confirm('¿Eliminar esta imagen?')) return;
    const r = await api('api_eliminar_imagen', { id:b.dataset.elimImg });
    if (r.ok) { b.parentElement.remove(); toast('Imagen eliminada'); } else toast('No se pudo eliminar la imagen', true);
  }));
};
const accionAdmin = async (action, data, sec, msgOk) => {
  const r = await api(action, data);
  if (fallo(r)) return toast(r?.msg || 'No se pudo completar la acción', true);
  if (msgOk) toast(msgOk);
  seccionAdmin(sec);
};
window.toggleProd = id => accionAdmin('api_admin_toggle_producto', {id}, 'productos');

window.formCampania = function(x) {
  modalForm(x ? 'Editar campaña' : 'Nueva campaña', `<div class="fgrid fld">
    <div class="full"><label>Nombre</label><input id="f_nom" maxlength="100" value="${esc(x?.nombre || '')}"></div>
    <div class="full"><label>Descripción</label><textarea id="f_des">${esc(x?.descripcion || '')}</textarea></div>
    <div><label>Estado</label>${selEstado(x ? x.estado : 1, 'Activa', 'Inactiva')}</div></div>`, async () => {
    const nombre = $('f_nom').value.trim();
    if (!nombre) return toast('Escribe un nombre', true);
    const r = await api('api_admin_guardar_campania', { id:x?.id || 0, nombre, descripcion:$('f_des').value, estado:$('f_est').value });
    if (!r.ok) return toast(r.msg || 'No se pudo guardar', true);
    cerrarForm(); toast('Campaña guardada'); seccionAdmin('campanias');
  });
};
window.toggleCamp = id => accionAdmin('api_admin_toggle_campania', {id}, 'campanias');
window.setDefaultCamp = id => accionAdmin('api_admin_campania_default', {id}, 'campanias', 'Campaña principal actualizada');

window.formCategoria = function(x) {
  modalForm(x ? 'Editar categoría' : 'Nueva categoría', `<div class="fgrid fld">
    <div class="full"><label>Nombre</label><input id="f_nom" maxlength="100" value="${esc(x?.nombre || '')}"></div>
    <div><label>Estado</label>${selEstado(x ? x.estado : 1, 'Activa', 'Inactiva')}</div></div>`, async () => {
    const nombre = $('f_nom').value.trim();
    if (!nombre) return toast('Escribe un nombre', true);
    const r = await api('api_admin_guardar_categoria', { id:x?.id || 0, nombre, estado:$('f_est').value });
    if (!r.ok) return toast(r.msg || 'No se pudo guardar', true);
    cerrarForm(); toast('Categoría guardada'); seccionAdmin('categorias');
  });
};
window.toggleCat = id => accionAdmin('api_admin_toggle_categoria', {id}, 'categorias');

window.formUsuario = function(u) {
  modalForm(u ? 'Editar usuario' : 'Nuevo usuario', `<div class="fgrid fld">
    <div><label>Nombre</label><input id="f_nom" maxlength="100" value="${esc(u?.nombre || '')}"></div>
    <div><label>Usuario</label><input id="f_usu" maxlength="50" value="${esc(u?.usuario || '')}"></div>
    <div><label>Contraseña ${u ? '(vacío = no cambiar)' : ''}</label><input type="password" id="f_pass" autocomplete="new-password"></div>
    <div><label>Rol</label><select id="f_rol"><option value="admin" ${u?.rol == 'admin' ? 'selected' : ''}>Administrador</option><option value="usuario" ${u?.rol == 'usuario' || !u ? 'selected' : ''}>Usuario</option></select></div>
    <div><label>Estado</label>${selEstado(u ? u.estado : 1, 'Activo', 'Inactivo')}</div></div>`, async () => {
    const nombre = $('f_nom').value.trim(), usuario = $('f_usu').value.trim();
    if (!nombre || !usuario) return toast('Completa el nombre y el usuario', true);
    const r = await api('api_admin_guardar_usuario', { id:u?.id || 0, nombre, usuario, password:$('f_pass').value, rol:$('f_rol').value, estado:$('f_est').value });
    if (!r.ok) return toast(r.msg, true);
    cerrarForm(); toast('Usuario guardado'); seccionAdmin('usuarios');
  });
};
window.toggleUser = id => accionAdmin('api_admin_toggle_usuario', {id}, 'usuarios');

// ============ ARRANQUE ============
(async function init() {
  await cargarConfig();
  await cargarSesion();
  pintarBadge();
  if (EN_ADMIN) await vistaAdmin(); else await vistaHome();
})();
