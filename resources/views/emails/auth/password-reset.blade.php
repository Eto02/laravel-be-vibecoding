Hello {{ $user->name }},

You requested a password reset. Use the token below in your app to reset your password.
This token expires in 60 minutes.

Token: {{ $token }}

If you did not request a password reset, no further action is required.
