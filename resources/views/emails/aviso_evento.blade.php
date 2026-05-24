<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Nuevo Evento - CAM #48</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px; color: #334155; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .header { background: linear-gradient(135deg, #009FE3, #007BB0); padding: 30px; text-align: center; color: white; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 800; }
        .content { padding: 30px; }
        .content p { line-height: 1.6; font-size: 16px; margin-bottom: 20px; }
        .event-details { background-color: #f1f5f9; border-left: 4px solid #A64B9D; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .event-details strong { color: #1e293b; display: inline-block; width: 90px; }
        .footer { background-color: #f8fafc; padding: 20px; text-align: center; font-size: 13px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Centro de Atención Múltiple #48</h1>
        </div>
        <div class="content">
            <p>Estimado Padre de Familia / Tutor,</p>
            <p>Por este medio nos ponemos en contacto con usted para informarle que se ha agendado una nueva actividad o reunión escolar a la cual está cordialmente invitado(a).</p>
            
            <div class="event-details">
                <p style="margin-bottom: 10px;"><strong>Actividad:</strong> {{ $nombreEvento }}</p>
                <p style="margin-bottom: 10px;"><strong>Fecha:</strong> {{ $fecha }}</p>
                <p style="margin-bottom: 0;"><strong>Ubicación:</strong> {{ $ubicacion }}</p>
            </div>
            
            <p>Le pedimos amablemente registrar este evento en su agenda. Su asistencia y puntualidad son de gran importancia para el desarrollo de nuestro alumnado.</p>
            <p>Atentamente,<br><strong>La Dirección Escolar</strong></p>
        </div>
        <div class="footer">
            Este es un mensaje generado automáticamente por el Sistema Integral de Control del CAM #48. Por favor, no responda a este correo.
        </div>
    </div>
</body>
</html>
