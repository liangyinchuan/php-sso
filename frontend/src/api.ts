export type User = {
  id: number
  username: string
  nickname: string
  email: string
  phone: string
  avatar: string
  is_admin: boolean
  status: number
  last_login_at: string | null
  last_login_ip: string
  created_at: string | null
  apps_count?: number
  apps?: AppMembership[]
}

export type AppItem = {
  id: number
  name: string
  code: string
  description: string
  logo: string
  homepage: string
  client_id: string
  client_type: 'public' | 'confidential'
  redirect_uris: string[]
  post_logout_uris: string[]
  scopes: string[]
  access_ttl: number
  refresh_ttl: number
  auto_provision: boolean
  require_consent: boolean
  status: number
  users_count: number
  created_at: string | null
  client_secret?: string
}

export type Profile = {
  department: string
  title: string
  employee_no: string
  extra: Record<string, string>
}

export type Member = {
  id: number
  app_id: number
  user_id: number
  username: string
  nickname: string
  email: string
  external_id: string
  roles: string[]
  profile: Profile
  status: number
}

export type AppMembership = {
  id: number
  name: string
  code: string
  homepage?: string
  logo?: string
  external_id: string
  roles: string[]
  profile: Profile
  status: number
  consented?: boolean
}

export type Page<T> = { list: T[]; total: number; page: number; page_size: number }

export type LogItem = {
  id: number
  username: string
  app_name: string
  event: string
  success: boolean
  ip: string
  detail: string
  created_at: string | null
}

type Envelope<T> = { code: number; message: string; data: T }

export async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
  const headers = new Headers(init.headers)
  if (init.body && !headers.has('Content-Type')) headers.set('Content-Type', 'application/json')
  const response = await fetch(path, { ...init, headers, credentials: 'include' })
  const text = await response.text()
  let body: Envelope<T> | null = null
  try {
    body = text ? JSON.parse(text) : null
  } catch {
    throw new Error('服务返回了无法解析的内容')
  }
  if (!response.ok || (body && typeof body.code === 'number' && body.code !== 0)) {
    throw new Error(body?.message || '请求失败')
  }
  return body?.data as T
}
