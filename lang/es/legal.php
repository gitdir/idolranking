<?php

return array (
  'document_label' => 'Información legal sobre la clasificación de ídolos',
  'footer_navigation' => 'Documentos legales',
  'footer_privacy' => 'política de privacidad',
  'footer_terms' => 'Condiciones de servicio',
  'effective_date_label' => 'Fecha de entrada en vigor',
  'effective_date_placeholder' => '[El operador debe completar: AAAA-MM-DD]',
  'privacy_title' => 'política de privacidad',
  'privacy_seo_title' => 'Política de privacidad | IdolRanking',
  'privacy_seo_description' => 'Aprende cómo IdolRanking gestiona los datos de la cuenta, el inicio de sesión con Google, las cookies, el análisis de visitantes, las publicaciones públicas y las solicitudes de privacidad.',
  'privacy_intro' => 'Esta política explica cómo IdolRanking procesa la información para operar el servicio, por qué se utiliza, cuánto tiempo se conserva, qué proveedores pueden procesarla y cómo se pueden realizar solicitudes. Los elementos entre corchetes deben ser verificados y completados por el operador antes de su publicación.',
  'privacy_sections' =>
  array (
    0 =>
    array (
      'title' => '1. Operador y contacto',
      'items' =>
      array (
        0 => 'Operador del servicio: [Se requiere revisión del operador: nombre de la entidad legal o persona física y datos de registro]',
        1 => 'Dirección comercial: [El operador debe completar: dirección postal completa]',
        2 => 'Responsable de privacidad o persona de contacto: [El operador debe completar: nombre, cargo y método de contacto]',
        3 => 'Correo electrónico de contacto para cuestiones de privacidad: help@idolranking.com — [El operador debe confirmar que esta bandeja de entrada está siendo supervisada y asignar un responsable]',
      ),
    ),
    1 =>
    array (
      'title' => '2. Información procesada y cómo se recopila',
      'paragraphs' =>
      array (
        0 => 'El proceso de registro por correo electrónico incluye una dirección de correo electrónico, un hash de contraseña, un apodo, el código de país seleccionado, el estado y la hora de verificación del correo electrónico y las marcas de tiempo de la cuenta. El servicio no almacena la contraseña en texto plano. También se puede almacenar la información de referencia cuando un miembro se registra a través de un enlace de referido.',
        1 => 'Si eliges iniciar sesión con Google, el servicio solicita los permisos de OpenID, correo electrónico y perfil. Recibe un identificador de cuenta de Google y una dirección de correo electrónico verificada para vincular o crear una cuenta. Durante el registro, se puede usar un nombre de usuario de Google como sugerencia temporal; este no se publica automáticamente en lugar del nombre de usuario que elijas.',
        2 => 'El servicio procesa las publicaciones de fans enviadas por los miembros, los comentarios, los mensajes de asistencia, los "me gusta" y las recomendaciones, los votos, las solicitudes de registro de ídolos/grupos, las opciones de idioma, las imágenes de perfil y los campos de perfil públicos, como el apodo, el código de miembro, el país y la imagen de perfil, para proporcionar esas funciones.',
        3 => 'Para el funcionamiento y la seguridad del servicio, la aplicación registra las rutas de las solicitudes, las cadenas de agente de usuario, la asociación de miembros y las marcas de tiempo. Los registros de acceso a páginas públicas no almacenan direcciones IP sin procesar, pero los historiales de intentos de inicio de sesión y los registros de solicitudes de restablecimiento de contraseña sí almacenan direcciones IP y cadenas de agente de usuario. Los registros del servidor web o de alojamiento también pueden contener direcciones IP; su contenido exacto y su retención dependen de la configuración del alojamiento: [El operador debe verificar los registros y la retención reales].',
        4 => 'La verificación de correo electrónico y la recuperación de contraseña pueden procesar la dirección de correo electrónico correspondiente, el estado de verificación, un token con hash que caduca, la hora de la solicitud y la dirección IP de la solicitud. La contraseña y el token en texto plano no se almacenan.',
      ),
    ),
    2 =>
    array (
      'title' => '3. Fines',
      'items' =>
      array (
        0 => 'Registro de cuenta e inicio de sesión por correo electrónico o Google, verificación de correo electrónico, recuperación de contraseña y seguridad de la cuenta.',
        1 => 'Proporcionar y moderar perfiles, publicaciones de fans, comentarios, mensajes de asistencia, "me gusta", recomendaciones y solicitudes de registro de ídolos/grupos.',
        2 => 'Administrar las recompensas de Heart y la votación de ídolos/grupos, detectar actividades duplicadas o abusivas y responder a las solicitudes de soporte.',
        3 => 'Medición de las visitas a la página pública y la fiabilidad del servicio. Las estadísticas diarias de visitantes utilizan el límite de fechas Asia/Seúl.',
        4 => 'Traducir publicaciones de fans, comentarios o mensajes de asistencia cuando un usuario solicita la traducción y almacenar en caché el resultado para su visualización.',
        5 => 'Cumplir con la ley, revisar informes de derechos, resolver disputas y proteger la seguridad del sistema.',
      ),
    ),
    3 =>
    array (
      'title' => '4. Cookies, sesiones y análisis de visitantes',
      'paragraphs' =>
      array (
        0 => 'El servicio utiliza una cookie de sesión para mantener el estado de inicio de sesión, la protección CSRF, la selección de idioma y el estado de las solicitudes relacionadas. El almacenamiento y la caducidad de la sesión dependen de la configuración actual del servidor. Una cookie independiente almacena las horas de visualización recientes de las publicaciones de los fans para limitar las visualizaciones duplicadas; esta cookie tiene una duración máxima de una hora.',
        1 => 'Las estadísticas de visitantes utilizan la cookie de origen idolranking_visitor, que almacena un identificador aleatorio y tiene una duración configurada de dos años. UV contabiliza un identificador una vez al día; PV contabiliza las navegaciones válidas a páginas HTML públicas. Los administradores, las cuentas ficticias y los bots identificados mediante marcadores de agente de usuario se excluyen de los totales de UV/PV. La clasificación del agente de usuario es imperfecta y no puede identificar todas las solicitudes automatizadas.',
        2 => 'El identificador de visitante no se deriva de una dirección IP. Los registros de seguridad de inicio de sesión independientes pueden contener direcciones IP. Restringir las cookies en su navegador puede afectar el inicio de sesión, el idioma guardado o la capacidad de distinguir a los visitantes únicos.',
      ),
    ),
    4 =>
    array (
      'title' => '5. Proveedores externos y transferencias internacionales',
      'paragraphs' =>
      array (
        0 => 'Google OAuth se utiliza para iniciar sesión en una cuenta de Google. Cuando un usuario solicita una traducción, el texto que se va a traducir y la información del idioma se envían a la API de traducción de Google Cloud. Si una publicación de un fan incluye una miniatura de un video de YouTube, el navegador puede solicitar la imagen directamente a los servidores de imágenes de YouTube.',
        1 => 'Los correos electrónicos de verificación y recuperación de contraseña pueden enviarse a través del proveedor de correo electrónico configurado para producción. La aplicación y la base de datos están alojadas en el entorno de Hostinger. Las entidades legales del proveedor, las categorías de datos, las ubicaciones, la retención, los subprocesadores y las medidas de seguridad de transferencia deben completarse a partir de los contratos y la configuración de producción correspondientes: [El operador debe completar la información del proveedor, el país y las medidas de seguridad]. Los sitios externos enlazados desde el servicio se rigen por sus propios avisos de privacidad.',
      ),
    ),
    5 =>
    array (
      'title' => '6. Retención y eliminación',
      'paragraphs' =>
      array (
        0 => 'La información de la cuenta y el contenido asociado se conservan según sea necesario para su funcionamiento y para mantener los registros de servicio. La aplicación actual no ofrece una función de auto-cierre de cuenta. El proceso para recibir y gestionar las solicitudes de cierre/eliminación, las publicaciones relacionadas, los registros de "Me gusta"/votos/denuncias y cualquier registro legalmente retenido es [El operador decidirá: alcance, excepciones, plazos y contacto responsable]. Los registros exigidos por ley o para proteger a otras personas podrán conservarse por separado o con acceso restringido según las normas aplicables.',
        1 => 'La aplicación está configurada para eliminar las filas del registro de acceso a páginas públicas después de 90 días. No se detectó ninguna limpieza automática para los historiales de inicio de sesión, los registros de IP de solicitudes de restablecimiento de contraseña ni los agregados diarios de UV/PV ni los identificadores de visitantes; sus períodos de retención exactos y las tareas de eliminación son [El operador debe definirlos por tipo de registro]. Las cachés de traducción pueden permanecer mientras el contenido original esté disponible.',
        2 => 'Las reglas de retención y eliminación de las pruebas de los informes de derechos y los archivos cargados son [El operador debe completar: retención por tipo de archivo, acceso autorizado y método de eliminación].',
      ),
    ),
    6 =>
    array (
      'title' => '7. Sus derechos y solicitudes',
      'paragraphs' =>
      array (
        0 => 'Actualmente, los miembros pueden editar cierta información de su perfil y su contraseña en Mi Página. Para solicitar acceso, corrección, eliminación, restricción o revocación del consentimiento conforme a la legislación aplicable, póngase en contacto con el responsable de privacidad una vez que el operador haya confirmado el canal y el proceso. Podemos verificar la identidad y limitar una solicitud si se ven afectados los derechos de otra persona o una obligación legal de retención de datos.',
        1 => 'La aplicación actual no incluye una función de autoeliminación de cuentas ni un plazo para la respuesta a las solicitudes. Antes de su publicación, el operador debe establecer el canal de entrada real, las comprobaciones de identidad, las reglas de gestión y el tiempo de respuesta: [Acción requerida por el operador].',
      ),
    ),
    7 =>
    array (
      'title' => '8. Contenido público y perfiles',
      'paragraphs' =>
      array (
        0 => 'Las publicaciones públicas, los comentarios y los campos de perfil, como el apodo, el país y la imagen de perfil, pueden ser visibles para los visitantes que no hayan iniciado sesión y para los motores de búsqueda. Las direcciones de correo electrónico, los hashes de contraseñas y los registros de inicio de sesión/seguridad no se muestran en los perfiles públicos. Tras la eliminación u ocultación del contenido, pueden quedar copias temporales en copias de seguridad, informes de derechos, cachés o registros legales; el operador debe definir dichos periodos.',
      ),
    ),
    8 =>
    array (
      'title' => '9. Medidas de seguridad',
      'paragraphs' =>
      array (
        0 => 'El servicio utiliza cifrado de contraseñas, protección contra falsificación de solicitudes, regeneración del ID de sesión tras el inicio de sesión, comprobaciones de autorización y almacenamiento de archivos públicos y privados por separado. La organización de seguridad responsable, el proceso de notificación de incidentes, el calendario de revisión de accesos y el contacto en caso de vulneración son [El operador debe verificar]. Ningún servicio de internet puede eliminar todos los riesgos de seguridad.',
      ),
    ),
    9 =>
    array (
      'title' => '10. Cambios en la política y en los datos de contacto.',
      'paragraphs' =>
      array (
        0 => 'Contacto de privacidad: help@idolranking.com ([El operador debe confirmar que esta es una dirección oficial supervisada]). No envíe contraseñas ni tokens de autenticación. Proporcione únicamente la información mínima de la cuenta necesaria para procesar su solicitud. El contacto responsable, la dirección, la ley aplicable, el plazo de respuesta y el proceso de notificación de cambios son [El operador debe completar].',
        1 => 'Cuando esta política cambie, el servicio actualizará esta página y su fecha de entrada en vigor. El operador deberá determinar cómo se gestionarán los avisos previos y cualquier renovación del consentimiento legalmente requerida: [Se requiere revisión del operador].',
      ),
    ),
  ),
  'terms_title' => 'Condiciones de servicio',
  'terms_seo_title' => 'Condiciones de servicio | IdolRanking',
  'terms_seo_description' => 'Lee las reglas para las cuentas de IdolRanking, las publicaciones de fans, los comentarios, las solicitudes de ídolos, las recompensas de corazones, las votaciones y el uso del servicio.',
  'terms_intro' => 'Estos términos describen las funciones que ofrece actualmente IdolRanking y las responsabilidades básicas de los usuarios y del operador. Los detalles legales y operativos entre corchetes deben ser finalizados por el operador.',
  'terms_sections' =>
  array (
    0 =>
    array (
      'title' => '1. Servicio y operador',
      'paragraphs' =>
      array (
        0 => 'IdolRanking es un servicio comunitario que ofrece clasificaciones de ídolos/grupos, publicaciones y comentarios de fans, mensajes de asistencia, recompensas de corazones y votaciones, y solicitudes de registro de ídolos/grupos.',
        1 => 'Operador: [Se requiere revisión del operador: nombre de la entidad legal o persona física]; dirección: [El operador debe completar]; contacto: help@idolranking.com ([El operador debe confirmar que este es un canal de contacto oficial]).',
      ),
    ),
    1 =>
    array (
      'title' => '2. Cuentas y requisitos de elegibilidad',
      'paragraphs' =>
      array (
        0 => 'El registro por correo electrónico requiere verificación. El registro en Google utiliza una dirección de correo electrónico verificada por Google y un identificador de cuenta. Los miembros deben proporcionar información precisa y proteger sus credenciales. Al registrarse en Google, el usuario elige su propio apodo y país.',
        1 => 'La edad mínima y los requisitos de consentimiento del tutor legal son [El operador deberá definirlos para cada jurisdicción aplicable]. No se debe presentar el servicio como apto para un grupo de edad específico hasta que se confirmen estas normas.',
      ),
    ),
    2 =>
    array (
      'title' => '3. Responsabilidades de los miembros y conducta prohibida',
      'items' =>
      array (
        0 => 'Utilizar la cuenta de otra persona o eludir la seguridad de la cuenta, la autenticación, las restricciones de acceso o los controles del servicio.',
        1 => 'Utilizar la automatización, múltiples cuentas o actividad coordinada para manipular las recompensas de Heart, los votos, las clasificaciones o las estadísticas de visitantes, u obtener un beneficio indebido.',
        2 => 'Publicar material ilegal, fraudulento, amenazante, acosador, que incite al odio, difamatorio, que invada la privacidad o que sea abusivo y dirigido a un objetivo específico.',
        3 => 'Enviar material que infrinja los derechos de autor, marcas registradas, derechos de imagen, privacidad o los términos de un servicio de terceros.',
        4 => 'Recopilación o extracción excesiva de datos, búsqueda de vulnerabilidades, transmisión de código malicioso o interrupción de sistemas.',
      ),
    ),
    3 =>
    array (
      'title' => '4. Publicaciones de fans, comentarios y contenido de usuario.',
      'paragraphs' =>
      array (
        0 => 'Los miembros deben contar con los derechos y permisos necesarios para publicar entradas de fans, comentarios, mensajes de asistencia, imágenes y solicitudes de registro, y deben respetar la ley y los derechos de los demás. El autor conserva sus derechos; la publicación no transfiere la propiedad al operador.',
        1 => 'Para operar el servicio, el miembro otorga al operador un permiso no exclusivo y limitado al servicio para almacenar y respaldar el contenido, convertirlo a los formatos necesarios, transmitirlo y mostrarlo a los usuarios y visitantes del servicio. La duración exacta de este permiso y la duración de las copias de respaldo/informes están sujetas a revisión legal por parte del operador. Este texto no autoriza el uso para publicidad ni la venta fuera del servicio.',
        2 => 'Una solicitud de registro de ídolos o grupos es una propuesta para su revisión, no una promesa de aprobación, tiempo de registro ni una recompensa específica. El operador puede revisar, editar o rechazar una solicitud por motivos de exactitud, duplicación o derechos de autor.',
      ),
    ),
    4 =>
    array (
      'title' => '5. Derechos de autor, derechos de imagen, informes y moderación',
      'paragraphs' =>
      array (
        0 => 'No publiques el trabajo, la fotografía, la imagen o la información personal de otra persona sin su permiso. El servicio ofrece la posibilidad de denunciar publicaciones, comentarios e imágenes de perfil de los fans. Incluye el contenido específico y el motivo de la denuncia. El operador podrá revisar el material y ocultarlo, restaurarlo, eliminarlo o solicitar más información.',
        1 => 'Los detalles requeridos para la notificación legal, los plazos de respuesta, las apelaciones y los procedimientos para infractores reincidentes son responsabilidad del operador, de acuerdo con la legislación aplicable. Las herramientas de denuncia implementadas no pretenden reemplazar todos los procesos legales de notificación y retirada de contenido.',
      ),
    ),
    5 =>
    array (
      'title' => '6. Recompensas por corazones y votación',
      'paragraphs' =>
      array (
        0 => 'Los corazones son un valor dentro del servicio que se utiliza para las recompensas de las actividades implementadas y para la votación de ídolos/grupos. No tienen valor monetario, no se pueden canjear ni transferir, y actualmente no se pueden comprar. Los tipos de recompensa, los rangos, los límites diarios y los requisitos de elegibilidad se rigen por la configuración de la función y del administrador; dicha configuración puede modificar las condiciones de las recompensas en el futuro.',
        1 => 'Los votos se procesan según las reglas de votación vigentes y el saldo de Heart disponible. Las recompensas o votos obtenidos mediante automatización, duplicación o actividad fraudulenta pueden ser revisados, corregidos o invalidados. El proceso de notificación y apelación para recompensas erróneas es [Se requiere revisión del operador: definir criterios de manejo].',
      ),
    ),
    6 =>
    array (
      'title' => '7. Medidas de contenido y de cuenta',
      'paragraphs' =>
      array (
        0 => 'Por motivos legales, de protección de derechos, seguridad o sospecha de infracción, el operador podrá ocultar o eliminar publicaciones, comentarios, imágenes o solicitudes de registro, ajustar las recompensas o restringir/suspender el acceso a la cuenta. En la medida de lo posible, el operador proporcionará una explicación y un método de contacto; los casos urgentes de seguridad o legales podrían requerir una actuación inmediata.',
        1 => 'La duración de la suspensión, el canal de apelación, el cierre/eliminación de cuentas a petición del miembro y la gestión de Heart no utilizados son [Determinados por el operador]. La aplicación actual no ofrece una función de autoeliminación de cuentas.',
      ),
    ),
    7 =>
    array (
      'title' => '8. Cambios en el servicio y disponibilidad',
      'paragraphs' =>
      array (
        0 => 'El operador podrá modificar o interrumpir funciones, pantallas, configuraciones de recompensas o la visualización de contenido por motivos legales, de seguridad, técnicos u operativos. El mantenimiento o las interrupciones del servicio podrían afectarlo temporalmente. Los servicios externos, como Google, los proveedores de correo electrónico y YouTube, son gestionados por sus respectivos proveedores.',
        1 => 'En la medida permitida por la ley aplicable, la responsabilidad podrá limitarse por fallas externas, conducta del usuario o eventos fuera del control razonable del operador. Nada de lo aquí expuesto limita los derechos obligatorios del consumidor o de privacidad. La ley aplicable y los términos de responsabilidad definitivos requieren [revisión legal del operador].',
      ),
    ),
    8 =>
    array (
      'title' => '9. Registros de cambios y aceptación',
      'paragraphs' =>
      array (
        0 => 'Los cambios se publicarán en esta página con su contenido y fecha de entrada en vigor. El operador debe definir el aviso previo, la fecha de entrada en vigor de los cambios para los miembros existentes y cuándo se requiere un consentimiento renovado: [El operador debe finalizarlo]. La aplicación actual no parece registrar la aceptación por versión de la política, por lo que el operador debe decidir cómo se presentará y registrará dicha aceptación.',
      ),
    ),
    9 =>
    array (
      'title' => '10. Contacto',
      'paragraphs' =>
      array (
        0 => 'Para términos, informes de contenido o restricciones de cuenta: help@idolranking.com ([El operador debe confirmar la persona responsable, la bandeja de entrada supervisada y el proceso de respuesta]). La identidad del operador, la dirección comercial, la ley aplicable, el lugar de resolución de disputas y el plazo de respuesta deben completarse antes de la publicación: [Acción requerida por el operador].',
      ),
    ),
  ),
);
