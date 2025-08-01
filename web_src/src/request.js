/**
 *
 */

import axios from 'axios'
import router from '@/router/index'
import { MessageBox } from 'element-ui'

axios.defaults.timeout = 60000

const request = (
  path,
  data = {},
  method = 'post',
  msgAlert = true,
  contentType = 'form'
) => {
  let url = DocConfig.server + path

  const userinfostr = localStorage.getItem('userinfo')
  if (userinfostr) {
    const userinfo = JSON.parse(userinfostr)
    if (userinfo && userinfo.user_token) {
      data.user_token = userinfo.user_token
    }
  }

  let axiosConfig = {
    url: url,
    method: method,
    data: new URLSearchParams(data),
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    }
  }

  if (contentType == 'json') {
    axiosConfig.data = data // 这里使用原始data，不经过URLSearchParams
    axiosConfig.headers['Content-Type'] = 'application/json'
  } else if (data instanceof FormData) {
    // 如果是FormData，直接使用，不转换
    axiosConfig.data = data
    axiosConfig.headers['Content-Type'] = 'multipart/form-data'
  }

  return new Promise((resolve, reject) => {
    axios(axiosConfig)
      .then(
        response => {
          if (msgAlert && response.data && response.data.error_code !== 0) {
            // 超时登录
            if (response.data.error_code === 10102) {
              var redirect = router.currentRoute.fullPath.repeat(1)
              if (redirect.indexOf('redirect=') > -1) {
                // 防止重复redirect
                return false
              }
              router.replace({
                path: '/user/login',
                query: { redirect: redirect }
              })
              reject(new Error('登录态无效'))
            } else {
              MessageBox.alert(response.data.error_message)
              return reject(new Error('业务级别的错误'))
            }
          }
          // 上面没有return的话，最后返回这个
          resolve(response.data)
        },
        err => {
          if (err.Cancel) {
            console.log(err)
          } else {
            reject(err)
          }
        }
      )
      .catch(err => {
        reject(err)
      })
  })
}

export default request
