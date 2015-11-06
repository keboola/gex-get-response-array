<?php
namespace Keboola\ExGenericModule;

use Keboola\GenericExtractor\Modules\ResponseModuleInterface;
use Keboola\Utils\Utils;
use Keboola\Juicer\Config\JobConfig,
	Keboola\Juicer\Exception\UserException,
	Keboola\Juicer\Common\Logger;

class FindResponseArray implements ResponseModuleInterface
{
	/**
     * Try to find the data array within $response.
     *
     * @param array|object $response
     * @param array $config
     * @return array
     * @todo support array of dataFields
     *     - would return object with results, changing the class' API
     *     - parse would just have to loop through if it returns an object
     *     - and append $type with the dataField
     * @deprecated Use response module
     */
    public function process($response, JobConfig $jobConfig)
    {
        $config = $jobConfig->getConfig();

        // If dataField doesn't say where the data is in a response, try to find it!
        if (!empty($config['dataField'])) {
            if (is_array($config['dataField'])) {
                if (empty($config['dataField']['path'])) {
                    throw new UserException("'dataField.path' must be set!");
                }

                $path = $config['dataField']['path'];
            } elseif (is_scalar($config['dataField'])) {
                $path = $config['dataField'];
            } else {
                throw new UserException("'dataField' must be either a path string or an object with 'path' attribute.");
            }

            $data = Utils::getDataFromPath($path, $response, ".");
            if (empty($data)) {
                Logger::log('warning', "dataField '{$path}' contains no data!");
            }

            // In case of a single object being returned
            if (!is_array($data)) {
                $data = [$data];
            }
        } elseif (is_array($response)) {
            // Simplest case, the response is just the dataset
            $data = $response;
        } elseif (is_object($response)) {
            // Find arrays in the response
            $arrays = [];
            foreach($response as $key => $value) {
                if (is_array($value)) {
                    $arrays[$key] = $value;
                } // TODO else {$this->metadata[$key] = json_encode($value);} ? return [$data,$metadata];
            }

            $arrayNames = array_keys($arrays);
            if (count($arrays) == 1) {
                $data = $arrays[$arrayNames[0]];
            } elseif (count($arrays) == 0) {
                Logger::log('warning', "No data array found in response! (endpoint: {$config['endpoint']})", [
                    'response' => json_encode($response)
                ]);
                $data = [];
            } else {
                $e = new UserException("More than one array found in response! Use 'dataField' parameter to specify a key to the data array. (endpoint: {$config['endpoint']}, arrays in response root: " . join(", ", $arrayNames) . ")");
                $e->setData([
                    'response' => json_encode($response),
                    'arrays found' => $arrayNames
                ]);
                throw $e;
            }
        } else {
            $e = new UserException('Unknown response from API.');
            $e->setData([
                'response' => json_encode($response)
            ]);
            throw $e;
        }

        return $data;
    }
}
