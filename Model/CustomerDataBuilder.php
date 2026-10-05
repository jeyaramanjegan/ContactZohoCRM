<?php
namespace Contact\ZohoCRM\Model;

use Magento\Customer\Model\Customer;

/**
 * Builds the flat customer array that ZohoClient::mapFields() maps to Zoho fields
 */
class CustomerDataBuilder
{
    /**
     * Prepare customer data for Zoho CRM
     *
     * @param Customer $customer
     * @return array
     */
    public function build(Customer $customer): array
    {
        $data = [
            'customer_id' => $customer->getId(),
            'website_id' => $customer->getWebsiteId(),
            'store_id' => $customer->getStoreId(),
            'firstname' => $customer->getFirstname(),
            'lastname' => $customer->getLastname(),
            'email' => $customer->getEmail(),
            'telephone' => $customer->getTelephone() ?? '',
            'dob' => $customer->getDob() ?? '',
            'gender' => $this->getGenderLabel($customer->getGender() ? (int)$customer->getGender() : null),
            'taxvat' => $customer->getTaxvat() ?? '',
            'company' => $customer->getCompany() ?? '',
            'created_at' => $customer->getCreatedAt(),
            'updated_at' => $customer->getUpdatedAt()
        ];

        $address = $customer->getDefaultBillingAddress();
        if ($address) {
            $data['address'] = [
                'street' => $address->getStreetFull() ?? '',
                'city' => $address->getCity() ?? '',
                'region' => $address->getRegion() ?? '',
                'country_id' => $address->getCountryId() ?? '',
                'postcode' => $address->getPostcode() ?? ''
            ];

            // Telephone and company live on the address, not the customer entity
            $data['telephone'] = $data['telephone'] ?: ($address->getTelephone() ?? '');
            $data['company'] = $data['company'] ?: ($address->getCompany() ?? '');
        }

        return $data;
    }

    /**
     * Get gender label
     *
     * @param int|null $gender
     * @return string
     */
    protected function getGenderLabel(?int $gender): string
    {
        $options = [
            1 => 'Male',
            2 => 'Female',
            3 => 'Not Specified'
        ];

        return $options[$gender] ?? 'Not Specified';
    }
}
