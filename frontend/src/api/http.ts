import axios from 'axios'

import { API_URL } from '@/config'

import { rejectWithSessionCheck } from './session'

export const http = axios.create({
  baseURL: API_URL.href,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
})

http.interceptors.response.use(undefined, rejectWithSessionCheck)
