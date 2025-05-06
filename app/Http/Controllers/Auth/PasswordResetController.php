<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class PasswordResetController extends Controller
{
    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        
        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $token = Str::random(64);
        $resetUrl = env('FRONTEND_URL') . '/reset-password?email=' . urlencode($request->email) . '&token=' . $token;

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => $token,
                'created_at' => now(),
                'expires_at' => now()->addMinutes(15)
            ]
        );

        $client = new Client();
        
        try {
            $htmlContent = "
                <h2>Restablecer Contraseña</h2>
                <p>Has solicitado restablecer la contraseña de tu cuenta.</p>
                <p>Haz clic en el enlace de abajo para restablecer tu contraseña:</p>
                <p><a href='{$resetUrl}'>Restablecer Contraseña</a></p>
                <p>Si no solicitaste esto, por favor ignora este correo.</p>
                <p>Este enlace expirará en 15 minutos.</p>
            ";

            $response = $client->post('https://api.brevo.com/v3/smtp/email', [
                'headers' => [
                    'accept' => 'application/json',
                    'api-key' => env('BREVO_API_KEY'),
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'sender' => [
                        'name' => 'Reportes Comunitarios',
                        'email' => env('MAIL_FROM_ADDRESS')
                    ],
                    'to' => [
                        [
                            'email' => $request->email,
                            'name' => $user->name
                        ]
                    ],
                    'subject' => 'Solicitud de Restablecimiento de Contraseña',
                    'htmlContent' => $htmlContent
                ]
            ]);

            return response()->json(['message' => 'Password reset link sent successfully']);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error sending email',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function reset(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed'
        ]);

        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$resetRecord) {
            return response()->json(['message' => 'Invalid or expired token'], 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->password = bcrypt($request->password);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password has been reset']);
    }
}
