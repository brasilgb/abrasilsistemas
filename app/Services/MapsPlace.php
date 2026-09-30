<?php

namespace App\Services;

/**
 * Identidade de um lugar do Google Maps (CRM-LEAD-02), lida só do texto da URL, sem chamada
 * externa. A URL inteira muda a cada busca (@lat,lng,zoom, parâmetros); estes identificadores não:
 *  - Place ID (!19sChIJ… ou place_id=): o id público do Google, sensível a maiúsculas;
 *  - FTID (!1s0x…:0x… ou ftid=): o id interno do lugar no Maps;
 *  - coordenadas do lugar (!3d<lat>!4d<lng>), não as do mapa exibido (@lat,lng).
 */
final readonly class MapsPlace
{
    public function __construct(
        public ?string $placeId = null,
        public ?string $featureId = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}

    public static function fromUrl(?string $url): self
    {
        $url = (string) $url;

        $placeId = preg_match('/(?:!19s|[?&](?:query_)?place_?id=|place_id:)([A-Za-z0-9_-]{4,255})/', $url, $match) === 1
            ? $match[1]
            : null;

        $featureId = preg_match('/(?:!1s|ftid=)(0x[0-9a-f]{1,16}:0x[0-9a-f]{1,16})/i', $url, $match) === 1
            ? strtolower($match[1])
            : null;

        [$latitude, $longitude] = preg_match('/!3d(-?\d{1,2}(?:\.\d+)?)!4d(-?\d{1,3}(?:\.\d+)?)/', $url, $match) === 1
            ? self::coordinates($match[1], $match[2])
            : [null, null];

        return new self($placeId, $featureId, $latitude, $longitude);
    }

    /**
     * Valores enviados explicitamente (extensão 1.4+). Inválidos viram null: a URL continua sendo a
     * fonte principal e um campo opcional estranho não derruba o lote.
     */
    public static function fromValues(mixed $placeId, mixed $latitude, mixed $longitude): self
    {
        $placeId = is_string($placeId) && preg_match('/^[A-Za-z0-9_-]{4,255}$/', $placeId) === 1 ? $placeId : null;
        [$latitude, $longitude] = is_numeric($latitude) && is_numeric($longitude)
            ? self::coordinates((string) $latitude, (string) $longitude)
            : [null, null];

        return new self($placeId, null, $latitude, $longitude);
    }

    /** Completa o que faltar com outra leitura do mesmo lugar (a URL tem precedência). */
    public function orElse(self $other): self
    {
        $hasCoordinates = $this->latitude !== null && $this->longitude !== null;

        return new self(
            $this->placeId ?? $other->placeId,
            $this->featureId ?? $other->featureId,
            $hasCoordinates ? $this->latitude : $other->latitude,
            $hasCoordinates ? $this->longitude : $other->longitude,
        );
    }

    public function hasIdentity(): bool
    {
        return $this->placeId !== null || $this->featureId !== null;
    }

    /**
     * Mesmo lugar quando algum identificador coincide; lugares diferentes quando os dois lados têm o
     * mesmo tipo de identificador e ele diverge. Sem identificador comparável, não se sabe (null).
     */
    public function samePlaceAs(self $other): ?bool
    {
        if (($this->placeId !== null && $this->placeId === $other->placeId)
            || ($this->featureId !== null && $this->featureId === $other->featureId)) {
            return true;
        }

        if (($this->placeId !== null && $other->placeId !== null)
            || ($this->featureId !== null && $other->featureId !== null)) {
            return false;
        }

        return null;
    }

    /** @return array{google_place_id: ?string, maps_feature_id: ?string, latitude: ?float, longitude: ?float} */
    public function toAttributes(): array
    {
        return [
            'google_place_id' => $this->placeId,
            'maps_feature_id' => $this->featureId,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }

    /** @return array{0: ?float, 1: ?float} */
    private static function coordinates(string $latitude, string $longitude): array
    {
        $lat = round((float) $latitude, 7);
        $lng = round((float) $longitude, 7);

        if (abs($lat) > 90 || abs($lng) > 180 || ($lat == 0.0 && $lng == 0.0)) {
            return [null, null];
        }

        return [$lat, $lng];
    }
}
