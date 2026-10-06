<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PublicLegalController extends Controller
{
    public function terms(): View
    {
        return view('legal.show', [
            'title' => 'Términos y condiciones',
            'eyebrow' => 'Uso responsable de MiCatalogo',
            'sections' => [
                ['title' => 'Cuenta y responsabilidad', 'body' => 'Cada cuenta debe usar información real y mantener sus credenciales protegidas. El propietario es responsable de los productos, precios, pedidos y comunicaciones publicados desde su tienda.'],
                ['title' => 'Catálogo y ventas', 'body' => 'MiCatalogo proporciona herramientas para publicar catálogos, gestionar inventario y registrar ventas. La confirmación de una venta debe reflejar una operación real y el usuario debe revisar existencias, precios y datos del cliente antes de confirmarla.'],
                ['title' => 'Uso permitido', 'body' => 'No se permite usar la plataforma para contenido ilegal, fraude, suplantación, spam o para infringir derechos de terceros.'],
                ['title' => 'Cambios del servicio', 'body' => 'Podemos mejorar, actualizar o retirar funciones. Los cambios importantes se comunicarán dentro de la plataforma cuando corresponda.'],
            ],
        ]);
    }

    public function privacy(): View
    {
        return view('legal.show', [
            'title' => 'Política de privacidad',
            'eyebrow' => 'Cómo cuidamos tu información',
            'sections' => [
                ['title' => 'Datos que usamos', 'body' => 'Usamos los datos de cuenta, tienda, productos, clientes y operaciones para prestar las funciones solicitadas, proteger la plataforma y mostrar el catálogo público que el propietario decida publicar.'],
                ['title' => 'Pedidos y WhatsApp', 'body' => 'Cuando un cliente prepara un pedido, MiCatalogo genera un enlace de WhatsApp con el número configurado por la tienda y los datos necesarios para continuar la conversación. El mensaje solo se envía si el usuario decide hacerlo desde WhatsApp.'],
                ['title' => 'Protección y acceso', 'body' => 'Aislamos los datos por cuenta y tienda, aplicamos controles de acceso y registramos las operaciones necesarias para mantener la integridad del inventario y las finanzas.'],
                ['title' => 'Tus solicitudes', 'body' => 'Puedes solicitar ayuda sobre tu cuenta o tus datos desde Soporte. Las solicitudes se atienden verificando la identidad y el contexto de la tienda.'],
            ],
        ]);
    }
}
