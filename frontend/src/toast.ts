import { reactive } from 'vue'

export const toast = reactive({ text: '', kind: 'ok' as 'ok' | 'err', n: 0 })
let timer = 0

export function notify(text: string, kind: 'ok' | 'err' = 'ok') {
  toast.text = text
  toast.kind = kind
  toast.n += 1
  window.clearTimeout(timer)
  timer = window.setTimeout(() => { toast.text = '' }, 3200)
}
