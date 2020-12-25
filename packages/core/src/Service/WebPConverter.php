<?php

namespace Pushword\Core\Service;

use WebPConvert\Convert\ConverterFactory;
use WebPConvert\Convert\Converters\Stack;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConversionSkippedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Options\GhostOption;

/**
 * Waiting for this PR being accepted :
 * https://github.com/rosell-dk/webp-convert/pull/251.
 */
class WebPConverter extends Stack
{
    protected $converterUsed;

    protected function doActualConvert()
    {
        $options = $this->options;

        $beginTimeStack = microtime(true);

        $anyRuntimeErrors = false;

        $converters = $options['converters'];
        if (\count($options['extra-converters']) > 0) {
            $converters = array_merge($converters, $options['extra-converters']);
            /*foreach ($options['extra-converters'] as $extra) {
                $converters[] = $extra;
            }*/
        }

        // preferred-converters
        if (\count($options['preferred-converters']) > 0) {
            foreach (array_reverse($options['preferred-converters']) as $prioritizedConverter) {
                foreach ($converters as $i => $converter) {
                    if (\is_array($converter)) {
                        $converterId = $converter['converter'];
                    } else {
                        $converterId = $converter;
                    }
                    if ($converterId == $prioritizedConverter) {
                        unset($converters[$i]);
                        array_unshift($converters, $converter);

                        break;
                    }
                }
            }
            // perhaps write the order to the log? (without options) - but this requires some effort
        }

        // shuffle
        if ($options['shuffle']) {
            shuffle($converters);
        }

        //$this->logLn(print_r($converters));
        //$options['converters'] = $converters;
        //$defaultConverterOptions = $options;
        $defaultConverterOptions = [];

        foreach ($this->options2->getOptionsMap() as $id => $option) {
            if ($option->isValueExplicitlySet() && ! ($option instanceof GhostOption)) {
                //$this->logLn('hi' . $id);
                $defaultConverterOptions[$id] = $option->getValue();
            }
        }

        //unset($defaultConverterOptions['converters']);
        //unset($defaultConverterOptions['converter-options']);
        $defaultConverterOptions['_skip_input_check'] = true;
        $defaultConverterOptions['_suppress_success_message'] = true;
        unset($defaultConverterOptions['converters']);
        unset($defaultConverterOptions['extra-converters']);
        unset($defaultConverterOptions['converter-options']);
        unset($defaultConverterOptions['preferred-converters']);
        unset($defaultConverterOptions['shuffle']);

//        $this->logLn('converters: ' . print_r($converters, true));

        //return;
        foreach ($converters as $converter) {
            if (\is_array($converter)) {
                $converterId = $converter['converter'];
                $converterOptions = isset($converter['options']) ? $converter['options'] : [];
            } else {
                $converterId = $converter;
                $converterOptions = [];
                if (isset($options['converter-options'][$converterId])) {
                    // Note: right now, converter-options are not meant to be used,
                    //       when you have several converters of the same type
                    $converterOptions = $options['converter-options'][$converterId];
                }
            }
            $converterOptions = array_merge($defaultConverterOptions, $converterOptions);
            /*
            if ($converterId != 'stack') {
                //unset($converterOptions['converters']);
                //unset($converterOptions['converter-options']);
            } else {
                //$converterOptions['converter-options'] =
                $this->logLn('STACK');
                $this->logLn('converterOptions: ' . print_r($converterOptions, true));
            }*/

            $beginTime = microtime(true);

            $this->ln();
            $this->logLn('Trying: '.$converterId, 'italic');

            $converter = ConverterFactory::makeConverter(
                $converterId,
                $this->source,
                $this->destination,
                $converterOptions,
                $this->logger
            );

            try {
                $converter->doConvert();

                //self::runConverterWithTiming($converterId, $source, $destination, $converterOptions, false, $logger);

                $this->logLn($converterId.' succeeded :)');
                $this->converterUsed = $converterId;
                //throw new ConverterNotOperationalException('...');
                return;
            } catch (ConverterNotOperationalException $e) {
                $this->logLn($e->getMessage());
            } catch (ConversionSkippedException $e) {
                $this->logLn($e->getMessage());
            } catch (ConversionFailedException $e) {
                $this->logLn($e->getMessage(), 'italic');
                $prev = $e->getPrevious();
                if (null !== $prev) {
                    $this->logLn($prev->getMessage(), 'italic');
                    $this->logLn(' in '.$prev->getFile().', line '.$prev->getLine(), 'italic');
                    $this->ln();
                }
                //$this->logLn($e->getTraceAsString());
                $anyRuntimeErrors = true;
            }
            $this->logLn($converterId.' failed in '.round((microtime(true) - $beginTime) * 1000).' ms');
        }

        $this->ln();
        $this->logLn('Stack failed in '.round((microtime(true) - $beginTimeStack) * 1000).' ms');

        // Hm, Scrutinizer complains that $anyRuntimeErrors is always false. But that is not true!
        if ($anyRuntimeErrors) {
            // At least one converter failed
            throw new ConversionFailedException('None of the converters in the stack could convert the image.');
        } else {
            // All converters threw a SystemRequirementsNotMetException
            throw new ConverterNotOperationalException('None of the converters in the stack are operational');
        }
    }

    public function getConverterUsed(): ?string
    {
        return $this->converterUsed;
    }
}
