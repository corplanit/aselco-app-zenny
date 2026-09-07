<p>Hello {{ $customer->name }},</p>

<p>
    {{ number_format((float) $transaction->amount, 2) }} AST was loaded to your ASELCO wallet.
</p>

<p>
    Reference: <strong>{{ $transaction->reference_no }}</strong><br>
    New balance: <strong>{{ number_format((float) $transaction->balance_after, 2) }} AST</strong>
</p>

<p>1 AST = ₱1. You can use this balance to pay your electric bill in the myASELCO app.</p>
