<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px; color: #334155; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .header { background: linear-gradient(135deg, #0ea5e9, #2563eb); padding: 30px 20px; text-align: center; color: white; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 900; letter-spacing: -0.5px; }
        .content { padding: 40px 30px; line-height: 1.6; }
        .greeting { font-size: 18px; font-weight: 700; color: #0f172a; margin-top: 0; }
        .btn { display: inline-block; background-color: #0ea5e9; color: white; text-decoration: none; padding: 14px 28px; border-radius: 12px; font-weight: bold; margin: 20px 0; font-size: 16px; transition: background-color 0.3s; }
        .btn:hover { background-color: #0284c7; }
        .footer { background-color: #f1f5f9; padding: 20px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>VINCULACIÓN A TELEGRAM</h1>
        </div>
        <div class="content">
            <p class="greeting">Estimado(a) {{ $tutor->t1_nombre }},</p>
            <p>El Centro de Atención Múltiple #48 le invita cordialmente a unirse a nuestro sistema automatizado de notificaciones escolares vía Telegram.</p>
            <p>A través de este medio, usted recibirá alertas instantáneas sobre la asistencia de su hijo(a), así como comunicados y recordatorios de eventos escolares importantes.</p>
            <p>Para completar su registro y vincular su cuenta, por favor haga clic en el siguiente botón desde su teléfono móvil que tenga instalada la aplicación Telegram:</p>
            
            <div style="text-align: center;">
                <a href="{{ $enlaceMagico }}" class="btn">🚀 Vincular mi cuenta de Telegram</a>
            </div>

            <p style="font-size: 13px; color: #64748b; margin-top: 30px;">
                Si el botón no funciona, copie y pegue el siguiente enlace en su navegador:<br>
                <a href="{{ $enlaceMagico }}" style="color: #0ea5e9;">{{ $enlaceMagico }}</a>
            </p>
            
            <p style="margin-top: 30px;">Atentamente,<br><strong>La Dirección Escolar del CAM #48</strong></p>
        </div>
        <div class="footer">
            Este es un mensaje generado automáticamente por el Sistema Integral de Control del CAM #48. Por favor, no responda a este correo.
        </div>
    </div>
</body>
</html>
