/**
 * Load leaflet-draw and patch L.GeometryUtil.readableArea (strict mode fix).
 */
import L from 'leaflet'
import 'leaflet-draw'

const defaultPrecision = {
  km: 2,
  ha: 2,
  m: 0,
  mi: 2,
  ac: 2,
  yd: 0,
  ft: 0,
  nm: 2,
}

function readableArea(area, isMetric, precision) {
  let areaStr
  let units
  let type
  precision = L.Util.extend({}, defaultPrecision, precision)

  if (isMetric) {
    units = ['ha', 'm']
    type = typeof isMetric
    if (type === 'string') {
      units = [isMetric]
    } else if (type !== 'boolean') {
      units = isMetric
    }

    if (area >= 1000000 && units.indexOf('km') !== -1) {
      areaStr = `${L.GeometryUtil.formattedNumber(area * 0.000001, precision.km)} km²`
    } else if (area >= 10000 && units.indexOf('ha') !== -1) {
      areaStr = `${L.GeometryUtil.formattedNumber(area * 0.0001, precision.ha)} ha`
    } else {
      areaStr = `${L.GeometryUtil.formattedNumber(area, precision.m)} m²`
    }
  } else {
    let a = area
    a /= 0.836127

    if (a >= 3097600) {
      areaStr = `${L.GeometryUtil.formattedNumber(a / 3097600, precision.mi)} mi²`
    } else if (a >= 4840) {
      areaStr = `${L.GeometryUtil.formattedNumber(a / 4840, precision.ac)} acres`
    } else {
      areaStr = `${L.GeometryUtil.formattedNumber(a, precision.yd)} yd²`
    }
  }

  return areaStr
}

L.GeometryUtil.readableArea = readableArea
