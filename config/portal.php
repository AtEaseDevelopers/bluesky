<?php

return [

    'company' => [
        'name' => env('PORTAL_COMPANY_NAME', env('APP_NAME', 'Bluesky Live Seafood')),
        'registration_no' => env('PORTAL_COMPANY_REG', '1130071.K'),
        'phone' => env('PORTAL_COMPANY_PHONE', ''),
        'email' => env('PORTAL_COMPANY_EMAIL', ''),
        'address' => env('PORTAL_COMPANY_ADDRESS', 'Jln 11, Kampung Baru Ampang, 68000 Ampang, Selangor.'),
    ],

    'kyc_note' => 'Additional documents might be required by our risk team upon KYC risk assessment.',

    'pages' => [
        'about-us' => <<<'TEXT'
We supply fresh and live seafood to restaurants, hotels, retailers, and food service businesses across Malaysia.

Our team focuses on reliable sourcing, careful handling, and timely delivery so your kitchen receives quality products you can serve with confidence. Whether you order regularly or place ad-hoc requests, we aim to make ordering straightforward through our customer portal.
TEXT,

        'return-refund' => <<<'TEXT'
1. Quality Concerns
If you receive products that are not in acceptable condition, please notify us within 24 hours of delivery with supporting photos and your order reference. We will review each case and may offer replacement, credit, or refund where appropriate.

2. Order Cancellations
Orders that have already been prepared, packed, or dispatched may not be eligible for cancellation. Contact us as early as possible if you need to amend or cancel an order.

3. Refund Method
Approved refunds will be processed using the original payment method or applied as account credit, depending on the payment type and customer account status.

4. Non-Returnable Items
Live seafood and perishable goods generally cannot be returned once accepted at delivery unless a quality issue is confirmed by our team.
TEXT,

        'shipping-fulfilment' => <<<'TEXT'
1. Delivery Areas
Deliveries are arranged based on your registered delivery address and assigned service area. Available delivery slots are shown during checkout.

2. Order Processing
Orders are processed after confirmation. Cut-off times and preparation windows may vary by product availability and delivery schedule.

3. Delivery & Handover
Please ensure someone is available to receive the order at the agreed delivery time. Delays caused by incorrect address details, restricted access, or failed handover may incur redelivery charges.

4. Live & Fresh Products
Live and fresh seafood requires prompt receipt and proper storage upon delivery. We are not responsible for product quality after successful handover to the customer or authorised receiver.

5. Fulfilment Changes
We reserve the right to adjust delivery timing due to traffic, weather, supply, or operational constraints. We will notify you when a significant change affects your order.
TEXT,

        'privacy' => <<<'TEXT'
1. Information We Collect
We collect information you provide when registering, placing orders, making payments, and contacting us. This may include your business name, contact details, delivery addresses, order history, and payment records.

2. How We Use Your Information
Your information is used to manage your account, process orders, arrange delivery, provide customer support, and meet legal or regulatory requirements.

3. Data Sharing
We do not sell your personal data. Information may be shared with service providers who help us operate our business, such as delivery partners and payment processors, only as needed to fulfil our services.

4. Data Security
We take reasonable steps to protect your information from unauthorised access, loss, or misuse. Please keep your login credentials secure and notify us if you suspect unauthorised account access.

5. Your Rights
You may request access to or correction of your account information by contacting us. We may retain certain records as required by law or for legitimate business purposes.

6. Policy Updates
We may update this policy from time to time. Continued use of the customer portal after changes are published constitutes acceptance of the updated policy.
TEXT,
    ],

];
