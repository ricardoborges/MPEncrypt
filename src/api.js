import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

const url = (path) => generateOcsUrl(`apps/mpencrypt${path}`)

export async function listRecipients() {
  const { data } = await axios.get(url('/recipients'))
  // OCS envelope: { ocs: { data } }
  return data?.ocs?.data?.items ?? data?.items ?? []
}

export async function createRecipient(payload) {
  const { data } = await axios.post(url('/recipients'), payload)
  return data?.ocs?.data ?? data
}

export async function deleteRecipient(id) {
  await axios.delete(url(`/recipients/${id}`))
}

export async function encryptFile(payload) {
  // payload: { recipientId, fileId?, filePath? }
  const { data } = await axios.post(url('/encrypt'), payload)
  return data?.ocs?.data ?? data
}
