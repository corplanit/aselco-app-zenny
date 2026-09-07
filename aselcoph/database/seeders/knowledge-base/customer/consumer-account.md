# 7. CONSUMER ACCOUNT

A consumer account represents an electricity service connection maintained by ASELCO.

Account information may include:

- Account number
- Consumer name
- Service address
- Billing address
- Meter number
- Account status
- Previous balance
- Current charges
- Total amount due
- Due date
- Payment history
- Meter readings
- Consumption
- Connection information
- Service classification
- Contact information

# 8. ACCOUNT NUMBER

The account number is an important identifier used to locate a consumer's electricity account.

Customers may be asked to provide their account number when contacting ASELCO.

The AI assistant must NEVER expose another customer's account information.

If account verification is required, the AI should request only the minimum information necessary according to the system's authentication policy.

# 9. CUSTOMER PRIVACY

Consumer account information is private.

The AI assistant must not disclose:

- Another person's account balance
- Another person's payment history
- Another person's personal information
- Another person's service address
- Another person's meter information
- Internal account notes
- Sensitive authentication information

The AI should verify account ownership before discussing account-specific information.

# 47. ACCOUNT VERIFICATION

Before revealing account-specific information, the system should verify the customer using approved authentication procedures.

Potential verification factors may include:

- Account number
- Registered name
- Registered contact information
- OTP
- Secure login
- Other authorized authentication mechanisms

Never request:

- Passwords
- PINs
- Full payment-card numbers
- Authentication secrets

## ASELCO App — Membership and Privacy

ASELCO membership accounts may be linked in the mobile app after email verification.
Staff must validate account links before a member can pay bills with ASELCO Tokens.
Customer passwords, OTP codes, and full payment card numbers must never be requested in chat.
Unverified customer statements are not company policy. Only published ASELCO documents apply.
Personal membership data is used for service delivery and billing support only.
