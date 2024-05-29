<?php

namespace XSDGenerator\Commands;

use Akeneo\Pim\ApiClient\AkeneoPimClientBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function FluidXml\fluidxml;

class Generator extends Command
{
    protected static $defaultName = 'app:generate';

    private $client;

    protected function configure()
    {
        $this
            ->setName('xsd:generate')
            ->addOption('family', 'f', InputOption::VALUE_OPTIONAL, 'Set the family code which xsd is going to be generated for', 'default')
            ->setDescription('Import products brands from Shopify');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->client = $this->buildAkeneoApi();

        $output->writeln('Generate XSD');

        $familyCode = $input->getOption('family');

        $mainAttributes = [
            'identifier' => 'string',
            'enabled' => 'boolean',
            'family' => 'string',
            'parent' => 'string',
            'categories' => 'array',
            'groups' => 'array',
            'values' => 'attributeType',
        ];

        // Create a new XMLWriter object
        $xmlWriter = new \XMLWriter();
        $xmlWriter->openURI(sprintf('%s.xs', $familyCode));
        $xmlWriter->startDocument('1.0', 'UTF-8');
        $xmlWriter->setIndent(true);
        $xmlWriter->startElement('xs:schema');
        $xmlWriter->writeAttribute('xmlns:xs', 'http://www.w3.org/2001/XMLSchema');

        $xmlWriter->setIndent(true);
        $xmlWriter->writeComment('main attributes');
        foreach ($mainAttributes as $attrCode => $attrType) {
            if (in_array($attrType, ['string'])) {
                // schema elements
                // Add an example element
                $xmlWriter->startElement('xs:element');
                $xmlWriter->writeAttribute('name', $attrCode);
                $xmlWriter->writeAttribute('type', 'xsd:'.$attrType);
                $xmlWriter->endElement();
            } elseif (in_array($attrType, ['array'])) {
                // Add an example array of strings
                $xmlWriter->startElement('xs:element');
                $xmlWriter->writeAttribute('name', $attrCode);
                $xmlWriter->startElement('xs:simpleType');
                $xmlWriter->startElement('xs:list');
                $xmlWriter->writeAttribute('itemType', 'xsd:string');
                $xmlWriter->endElement();
                $xmlWriter->endElement();
                $xmlWriter->endElement();
            } elseif (in_array($attrType, ['boolean'])) {
                // Add an example boolean element
                $xmlWriter->startElement('xs:element');
                $xmlWriter->writeAttribute('name', $attrCode);
                $xmlWriter->writeAttribute('type', 'xsd:boolean');
                $xmlWriter->endElement();
            } elseif (in_array($attrType, ['attributeType'])) {
                // Add an example boolean element
                $xmlWriter->startElement('xs:element');
                $xmlWriter->writeAttribute('name', $attrCode);
                $xmlWriter->writeAttribute('type', 'xsd:attributeType');
                $xmlWriter->endElement();
            }
        }


        $data = $this->client->getFamilyApi()->get($familyCode);

        $mapTypes = [
            'pim_catalog_text' => 'string',
            'pim_catalog_metric' => 'string',
            'pim_catalog_simpleselect' => 'string',
            'akeneo_reference_entity' => 'string',
            'pim_catalog_multiselect' => 'string',
            'pim_catalog_textarea' => 'string',
            'pim_catalog_identifier' => 'string',
            'pim_catalog_boolean' => 'boolean',
            'pim_catalog_table' => 'string',
            'akeneo_reference_entity_collection' => 'string',
            'pim_catalog_asset_collection' => 'string',
            'pim_catalog_number' => 'decimal',
            'pim_catalog_date' => 'date',
        ];

        $type = [];
        foreach ($data['attributes'] as $attr) {
            $xmlWriter->writeComment('attribute for '.$attr);
            $data = $this->client->getAttributeApi()->get($attr);
            $xmlWriter->startElement('xs:simpleType');
            $xmlWriter->writeAttribute('name', $data['code']);
            $xmlWriter->startElement('xs:restriction');
            $xmlWriter->writeAttribute('base', 'xsd:string');
            $xmlWriter->endElement();
            $xmlWriter->endElement();

        }

        $xmlWriter->writeComment("attribute value type");

        // Define complexType localizedText
        $xmlWriter->startElement('xs:complexType');
        $xmlWriter->writeAttribute('name', 'attribute');
        $xmlWriter->startElement('xs:sequence');
        $xmlWriter->startElement('xs:element');
        $xmlWriter->writeAttribute('name', 'data');
        $xmlWriter->writeAttribute('type', 'xsd:string');
        $xmlWriter->endElement();
        $xmlWriter->startElement('xs:element');
        $xmlWriter->writeAttribute('name', 'locale');
        $xmlWriter->writeAttribute('type', 'xsd:string');
        $xmlWriter->endElement();
        $xmlWriter->startElement('xs:element');
        $xmlWriter->writeAttribute('name', 'scope');
        $xmlWriter->writeAttribute('type', 'xsd:string');
        $xmlWriter->writeAttribute('minOccurs', '0');
        $xmlWriter->endElement();
        $xmlWriter->startElement('xs:element');
        $xmlWriter->writeAttribute('name', 'attribute_type');
        $xmlWriter->writeAttribute('type', 'xsd:string');
        $xmlWriter->endElement();
        $xmlWriter->endElement();
        $xmlWriter->endElement();

        // End schema element
        $xmlWriter->endElement();

        // End document
        $xmlWriter->endDocument();
        $xmlWriter->flush();

        return Command::SUCCESS;
    }

    private function buildAkeneoApi()
    {
        $clientBuilder = new AkeneoPimClientBuilder($_ENV['API_URL']);

        if (!file_exists('/tmp/akeneo_token.tmp')) {

            $client = $clientBuilder->buildAuthenticatedByPassword(
                $_ENV['API_CLIENT_ID'],
                $_ENV['API_SECRET'],
                $_ENV['API_USER'],
                $_ENV['API_PASS']
            );

        } else {
            $credentials = file_get_contents('/tmp/akeneo_token.tmp');
            list($token, $refreshToken) = explode(':', $credentials);
            $client = $clientBuilder->buildAuthenticatedByToken($_ENV['API_CLIENT_ID'], $_ENV['API_SECRET'], $token, $refreshToken);
            file_put_contents('/tmp/akeneo_token.tmp', $client->getToken() . ':' . $client->getRefreshToken());
        }

        return $client;
    }
}