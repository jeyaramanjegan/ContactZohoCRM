# ContactZohoCRM
Magento 2 integration module that connects the store with Zoho CRM to synchronize customer data. It uses OAuth 2.0 authentication and Zoho CRM APIs to create or update customer records and keep Magento and Zoho customer information synchronized.

# ContactZohoCRM

Magento 2 integration module for connecting Magento customers with Zoho CRM.

## Overview

**ContactZohoCRM** integrates Magento 2 with Zoho CRM to synchronize customer information between the two systems.

The module uses **OAuth 2.0** for secure authorization and Zoho CRM APIs to create or update customer records.

## Features

* Connect Magento 2 with Zoho CRM
* OAuth 2.0 authentication
* Secure access and refresh token handling
* Create customer records in Zoho CRM
* Update existing customer records
* Identify existing customers using Zoho Contact ID
* Synchronize Magento customer information with Zoho CRM
* Magento Admin configuration for integration settings
* API error handling and logging

## Integration Flow

```text
Magento 2
   |
   | OAuth 2.0
   v
Zoho CRM Authorization
   |
   | Access Token
   v
Zoho CRM API
   |
   +---- Create Contact
   |
   +---- Update Contact
   |
   +---- Retrieve Contact
```

## Requirements

* Magento 2
* PHP version supported by your Magento version
* Zoho CRM account
* Zoho OAuth Client credentials

## Installation

Place the module under:

```text
app/code/Contact/ZohoCRM
```

Then run:

```bash
php bin/magento module:enable Contact_ZohoCRM
php bin/magento setup:upgrade
php bin/magento cache:flush
```

For production mode:

```bash
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
```

## Zoho CRM Configuration

Configure the Zoho CRM OAuth credentials in Magento Admin.

Typical OAuth configuration includes:

* Client ID
* Client Secret
* Redirect URI
* Authorization Code
* Access Token
* Refresh Token
* Zoho CRM API URL

The refresh token is stored and used to obtain a new access token when the current access token expires.

## Customer Synchronization

When a Magento customer needs to be synchronized:

1. Magento prepares the customer information.
2. The integration checks whether the customer already exists in Zoho CRM.
3. If an existing Zoho Contact ID is available, the contact can be updated.
4. If the customer does not exist, a new contact can be created.
5. The Zoho response is processed and logged where required.

## API Operations

The integration can use Zoho CRM APIs for operations such as:

```text
Create Contact
Update Contact
Get Contact
```

The module can also use Zoho's **Upsert** API when the business requirement is to create a record if it does not exist or update it when a matching record is found.

## Security

* OAuth 2.0 is used instead of storing a Zoho user password.
* Access tokens are used for API requests.
* Refresh tokens are used to obtain new access tokens.
* Sensitive credentials should not be committed to source control.

## Error Handling

The module handles common integration issues such as:

* Expired access tokens
* Invalid OAuth credentials
* Zoho API errors
* Invalid customer data
* Network/API request failures

Errors can be logged using Magento's logging mechanism for troubleshooting.

## Development

Recommended Magento development commands:

```bash
php bin/magento cache:flush
php bin/magento indexer:reindex
php bin/magento setup:upgrade
```

## License

This project is intended for development and integration purposes.
