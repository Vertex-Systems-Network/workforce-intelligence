export type AttendancePolicyLite={
  require_geolocation:boolean
  require_geofence:boolean
}

export type AttendanceActionLocation={
  source:'web'|'mobile'
  latitude?:number
  longitude?:number
  accuracy_meters?:number
}

/** Handles the is mobile source operation for the WorkIntel client. */ function isMobileSource(){
  return window.matchMedia?.('(pointer: coarse)').matches || window.matchMedia?.('(max-width: 800px)').matches || window.matchMedia?.('(display-mode: standalone)').matches
}

/** Handles the attendance action location operation for the WorkIntel client. */ export async function attendanceActionLocation(policy?:AttendancePolicyLite|null):Promise<AttendanceActionLocation>{
  const source:'web'|'mobile'=isMobileSource()?'mobile':'web'
  if(!policy?.require_geolocation&&!policy?.require_geofence)return {source}
  if(!('geolocation' in navigator))throw new Error('This device does not provide location access. Ask your administrator to change the attendance location policy or use a supported device.')
  const position=await new Promise<GeolocationPosition>((resolve,reject)=>navigator.geolocation.getCurrentPosition(resolve,error=>reject(new Error(error.message||'Location permission is required to mark attendance.')),{enableHighAccuracy:true,timeout:12000,maximumAge:30000}))
  return {source,latitude:position.coords.latitude,longitude:position.coords.longitude,accuracy_meters:position.coords.accuracy}
}
